<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Closure;
use DoppioGancio\MockedClient\Route\Exception\IncompleteRoute;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function assert;
use function fopen;
use function is_resource;
use function sprintf;

abstract class Builder
{
    protected string|null $method = null;
    protected string|null $path   = null;

    public function __construct(
        protected readonly ResponseFactoryInterface $responseFactory,
        protected readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function withMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function withPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param T                              $message
     * @param array<string, string|string[]> $headers
     *
     * @return T
     *
     * @template T of MessageInterface
     */
    protected function addHeaders(MessageInterface $message, array $headers): MessageInterface
    {
        foreach ($headers as $header => $value) {
            $message = $message->withHeader($header, $value);
        }

        return $message;
    }

    /** @param array<string, string|string[]> $headers */
    protected function buildResponseFromFile(string $file, int $httpStatus, array $headers): ResponseInterface
    {
        $fp = fopen($file, 'rb');
        assert(is_resource($fp), sprintf('File not found: %s', $file));
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStreamFromResource($fp));

        return $this->addHeaders($response, $headers);
    }

    /** @param array<string, string|string[]> $headers */
    protected function buildResponseFromString(string $content, int $httpStatus, array $headers): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStream($content));

        return $this->addHeaders($response, $headers);
    }

    /** @throws IncompleteRoute */
    protected function buildRoute(
        string|null $method = null,
        string|null $path = null,
        Closure|null $handler = null,
    ): Route {
        if ($method === null) {
            throw new IncompleteRoute('Set parameter \"method\" before to build');
        }

        if ($path === null) {
            throw new IncompleteRoute('Set parameter \"path\" before to build');
        }

        if ($handler === null) {
            throw new IncompleteRoute('Set parameter \"handler\" before to build');
        }

        return new Route($method, $path, $handler);
    }
}
