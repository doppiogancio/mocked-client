<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Closure;
use DoppioGancio\MockedClient\Exception\FileNotFound;
use DoppioGancio\MockedClient\Exception\NetworkFailure;
use DoppioGancio\MockedClient\Exception\TooManyCalls;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function count;
use function file_get_contents;
use function is_file;
use function is_readable;
use function is_string;
use function json_encode;
use function pathinfo;
use function strtolower;

use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * What a stub answers with. Every factory below produces a description, not a
 * response: the actual PSR-7 response, and above all its body stream, is built
 * fresh for each incoming request, so a route called twice does not hand out a
 * stream that the first call already consumed.
 */
final class MockResponse
{
    /** @param Closure(RequestInterface, ResponseFactoryInterface, StreamFactoryInterface): ResponseInterface $resolver */
    private function __construct(private readonly Closure $resolver)
    {
    }

    /** @param array<string, string|string[]> $headers */
    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self(
            static fn (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ): ResponseInterface => self::build($responseFactory, $streamFactory, $body, $status, $headers),
        );
    }

    /**
     * @param array<array-key, mixed>|object $data
     * @param array<string, string|string[]> $headers
     */
    public static function json(array|object $data, int $status = 200, array $headers = []): self
    {
        $headers['content-type'] ??= 'application/json';

        return self::text(json_encode($data, JSON_THROW_ON_ERROR), $status, $headers);
    }

    /**
     * The file is checked here rather than at request time, so an unreadable
     * fixture fails on the line that declared it.
     *
     * @param array<string, string|string[]> $headers
     *
     * @throws FileNotFound
     */
    public static function file(string $path, int $status = 200, array $headers = []): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new FileNotFound($path);
        }

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
            $headers['content-type'] ??= 'application/json';
        }

        $contents = null;

        return new self(
            static function (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ) use (
                &$contents,
                $path,
                $status,
                $headers,
            ): ResponseInterface {
                $contents ??= (string) file_get_contents($path);

                return self::build($responseFactory, $streamFactory, $contents, $status, $headers);
            },
        );
    }

    /** @param array<string, string|string[]> $headers */
    public static function status(int $status, array $headers = []): self
    {
        return self::text('', $status, $headers);
    }

    /** Wraps a ready made PSR-7 response. Its body is copied, so it stays reusable across calls. */
    public static function psr7(ResponseInterface $response): self
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = $body->getContents();

        return new self(
            static function (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ) use (
                $response,
                $contents,
            ): ResponseInterface {
                $fresh = $responseFactory
                    ->createResponse($response->getStatusCode(), $response->getReasonPhrase())
                    ->withProtocolVersion($response->getProtocolVersion())
                    ->withBody($streamFactory->createStream($contents));

                foreach ($response->getHeaders() as $name => $values) {
                    $fresh = $fresh->withHeader($name, $values);
                }

                return $fresh;
            },
        );
    }

    /**
     * Builds the answer from the request. The callback may return another
     * MockResponse, a PSR-7 response, or a plain string body.
     *
     * @param callable(RequestInterface): (self|ResponseInterface|string) $factory
     */
    public static function using(callable $factory): self
    {
        return new self(
            static function (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ) use ($factory): ResponseInterface {
                $produced = $factory($request);

                if (is_string($produced)) {
                    $produced = self::text($produced);
                }

                if ($produced instanceof ResponseInterface) {
                    return $produced;
                }

                return $produced->resolve($request, $responseFactory, $streamFactory);
            },
        );
    }

    /** One response per call, in order. The call after the last one throws TooManyCalls. */
    public static function sequence(self ...$responses): self
    {
        $cursor = 0;

        return new self(
            static function (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ) use (
                $responses,
                &$cursor,
            ): ResponseInterface {
                if ($cursor >= count($responses)) {
                    throw new TooManyCalls($request, count($responses));
                }

                return $responses[$cursor++]->resolve($request, $responseFactory, $streamFactory);
            },
        );
    }

    /** Simulates a transport failure, for testing retries, timeouts and circuit breakers. */
    public static function failure(string $message = 'Simulated network failure'): self
    {
        return new self(
            static function (
                RequestInterface $request,
                ResponseFactoryInterface $responseFactory,
                StreamFactoryInterface $streamFactory,
            ) use ($message): ResponseInterface {
                throw new NetworkFailure($request, $message);
            },
        );
    }

    /** @internal */
    public function resolve(
        RequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): ResponseInterface {
        return ($this->resolver)($request, $responseFactory, $streamFactory);
    }

    /** @param array<string, string|string[]> $headers */
    private static function build(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        string $body,
        int $status,
        array $headers,
    ): ResponseInterface {
        $response = $responseFactory
            ->createResponse($status)
            ->withBody($streamFactory->createStream($body));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
