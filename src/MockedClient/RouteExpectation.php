<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Closure;
use DoppioGancio\MockedClient\Route\CallbackResponse;
use DoppioGancio\MockedClient\Route\ConditionalResponse;
use DoppioGancio\MockedClient\Route\Exception\FileNotFound;
use DoppioGancio\MockedClient\Route\Exception\ResponseNotFound;
use DoppioGancio\MockedClient\Route\Exception\TooManyConsecutiveCalls;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function count;
use function fopen;
use function is_resource;
use function json_encode;
use function parse_str;

/**
 * Everything a single mocked route can respond with: a static response, a
 * queue of responses consumed one per call, a response chosen by query
 * string or by a callback matching the request, or a fully custom handler.
 * Returned by MockedClient::get()/post()/... and built fluently.
 */
final class RouteExpectation
{
    private Closure|null $customHandler = null;

    /** @var CallbackResponse[] */
    private array $callbackResponses = [];

    /** @var ConditionalResponse[] */
    private array $conditionalResponses = [];

    /** @var ResponseInterface[] */
    private array $sequentialResponses              = [];
    private int $sequentialCursor                   = 0;
    private ResponseInterface|null $defaultResponse = null;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Calling this once makes the route always answer with the same
     * response. Calling it more than once queues the responses: each call
     * to the route consumes the next one, and TooManyConsecutiveCalls is
     * thrown once the queue is exhausted.
     *
     * @param array<string, string|string[]> $headers
     */
    public function respondWith(string $content, int $httpStatus = 200, array $headers = []): self
    {
        return $this->respondWithResponse($this->buildResponseFromString($content, $httpStatus, $headers));
    }

    /**
     * @param array<int|string, mixed>       $data
     * @param array<string, string|string[]> $headers
     */
    public function respondWithJson(array|object $data, int $httpStatus = 200, array $headers = []): self
    {
        $headers['content-type'] ??= 'application/json';

        return $this->respondWith((string) json_encode($data), $httpStatus, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @throws FileNotFound
     */
    public function respondWithFile(string $file, int $httpStatus = 200, array $headers = []): self
    {
        return $this->respondWithResponse($this->buildResponseFromFile($file, $httpStatus, $headers));
    }

    public function respondWithResponse(ResponseInterface $response): self
    {
        $this->sequentialResponses[] = $response;
        $this->defaultResponse     ??= $response;

        return $this;
    }

    /** A full escape hatch: build the response yourself from the request. */
    public function respondUsing(Closure $handler): self
    {
        $this->customHandler = $handler;

        return $this;
    }

    /**
     * Respond with this response only when the request's query string
     * contains (at least) the given parameters, e.g. "code=it&page=2".
     *
     * @param array<string, string|string[]> $headers
     */
    public function respondWhen(string $queryString, string $content, int $httpStatus = 200, array $headers = []): self
    {
        $response = $this->buildResponseFromString($content, $httpStatus, $headers);

        return $this->respondWhenResponse($queryString, $response);
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @throws FileNotFound
     */
    public function respondWhenFile(string $queryString, string $file, int $httpStatus = 200, array $headers = []): self
    {
        return $this->respondWhenResponse($queryString, $this->buildResponseFromFile($file, $httpStatus, $headers));
    }

    public function respondWhenResponse(string $queryString, ResponseInterface $response): self
    {
        parse_str($queryString, $parameters);
        $this->conditionalResponses[] = new ConditionalResponse($parameters, $response);

        return $this;
    }

    /**
     * Respond with this response only when the callback returns true for
     * the incoming request.
     *
     * @param callable(RequestInterface):bool $matcher
     * @param array<string, string|string[]>  $headers
     */
    public function respondIf(callable $matcher, string $content, int $httpStatus = 200, array $headers = []): self
    {
        return $this->respondIfResponse($matcher, $this->buildResponseFromString($content, $httpStatus, $headers));
    }

    /**
     * @param callable(RequestInterface):bool $matcher
     * @param array<string, string|string[]>  $headers
     *
     * @throws FileNotFound
     */
    public function respondIfFile(callable $matcher, string $file, int $httpStatus = 200, array $headers = []): self
    {
        return $this->respondIfResponse($matcher, $this->buildResponseFromFile($file, $httpStatus, $headers));
    }

    /** @param callable(RequestInterface):bool $matcher */
    public function respondIfResponse(callable $matcher, ResponseInterface $response): self
    {
        $this->callbackResponses[] = new CallbackResponse($matcher, $response);

        return $this;
    }

    /** @throws ResponseNotFound|TooManyConsecutiveCalls */
    public function handle(RequestInterface $request): ResponseInterface
    {
        if ($this->customHandler !== null) {
            return ($this->customHandler)($request);
        }

        foreach ($this->callbackResponses as $callbackResponse) {
            if (($callbackResponse->getCallback())($request)) {
                return $callbackResponse->getResponse();
            }
        }

        parse_str($request->getUri()->getQuery(), $requestParameters);
        foreach ($this->conditionalResponses as $conditionalResponse) {
            if ($conditionalResponse->matchAgainst($requestParameters)) {
                return $conditionalResponse->response;
            }
        }

        if (count($this->sequentialResponses) > 1) {
            return $this->nextSequentialResponse($request);
        }

        if ($this->defaultResponse !== null) {
            return $this->defaultResponse;
        }

        throw new ResponseNotFound();
    }

    /** @throws TooManyConsecutiveCalls */
    private function nextSequentialResponse(RequestInterface $request): ResponseInterface
    {
        if ($this->sequentialCursor >= count($this->sequentialResponses)) {
            throw new TooManyConsecutiveCalls($request, $this->sequentialResponses);
        }

        return $this->sequentialResponses[$this->sequentialCursor++];
    }

    /** @param array<string, string|string[]> $headers */
    private function addHeaders(ResponseInterface $response, array $headers): ResponseInterface
    {
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @throws FileNotFound
     */
    private function buildResponseFromFile(string $file, int $httpStatus, array $headers): ResponseInterface
    {
        $fp = @fopen($file, 'rb');
        if (! is_resource($fp)) {
            throw new FileNotFound($file);
        }

        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStreamFromResource($fp));

        return $this->addHeaders($response, $headers);
    }

    /** @param array<string, string|string[]> $headers */
    private function buildResponseFromString(string $content, int $httpStatus, array $headers): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStream($content));

        return $this->addHeaders($response, $headers);
    }
}
