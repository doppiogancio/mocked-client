<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use DoppioGancio\MockedClient\Route\Exception\IncompleteRoute;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function assert;
use function fopen;
use function is_resource;

trait Builder
{
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected ?string $method = null;
    protected ?string $path   = null;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
    }

    public function new(): self
    {
        return new self($this->responseFactory, $this->streamFactory);
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

    /**
     * @param array<string, string|string[]> $headers
     */
    protected function buildResponseFromFile(string $file, int $httpStatus, array $headers): ResponseInterface
    {
        $fp = fopen($file, 'rb');
        assert(is_resource($fp));
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStreamFromResource($fp));

        return $this->addHeaders($response, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    protected function buildResponseFromString(string $content, int $httpStatus, array $headers): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStream($content));

        return $this->addHeaders($response, $headers);
    }

    protected function buildRoute(?string $method = null, ?string $path = null, ?callable $handler = null): Route
    {
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
