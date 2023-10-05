<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackRouteBuilder extends Builder
{
    /** @var CallbackResponse[]  */
    private array $responses = [];

    public function new(): self
    {
        return new self($this->responseFactory, $this->streamFactory);
    }

    /**
     * @param callable(RequestInterface      $request):bool $callback
     * @param array<string, string|string[]> $headers
     *
     * @return $this
     */
    public function withStringResponse(
        callable $callback,
        string $content,
        int $httpStatus = 200,
        array $headers = [],
    ): self {
        $response = $this->buildResponseFromString($content, $httpStatus, $headers);

        return $this->withResponse($callback, $response);
    }

    /**
     * @param callable(RequestInterface      $request):bool $callback
     * @param array<string, string|string[]> $headers
     *
     * @return $this
     */
    public function withFileResponse(callable $callback, string $file, int $httpStatus = 200, array $headers = []): self
    {
        $response = $this->buildResponseFromFile($file, $httpStatus, $headers);

        return $this->withResponse($callback, $response);
    }

    /**
     * @param callable(RequestInterface $request):bool $callback
     *
     * @return $this
     */
    public function withResponse(callable $callback, ResponseInterface $response): self
    {
        $this->responses[] = new CallbackResponse($callback, $response);

        return $this;
    }

    /** @throws Exception\IncompleteRoute */
    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            (new CallbackRouteHandler($this->responses))(...),
        );
    }
}
