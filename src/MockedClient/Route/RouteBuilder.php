<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RouteBuilder extends Builder
{
    protected string|null $method   = null;
    protected string|null $path     = null;
    protected Closure|null $handler = null;

    public function new(): self
    {
        return new self($this->responseFactory, $this->streamFactory);
    }

    /** @param array<string, string|string[]> $headers */
    public function withStringResponse(string $content, int $httpStatus = 200, array $headers = []): self
    {
        $response = $this->buildResponseFromString($content, $httpStatus, $headers);

        return $this->withResponse($response);
    }

    /** @param array<string, string|string[]> $headers */
    public function withFileResponse(string $file, int $httpStatus = 200, array $headers = []): self
    {
        $response = $this->buildResponseFromFile($file, $httpStatus, $headers);

        return $this->withResponse($response);
    }

    public function withResponse(ResponseInterface $response): self
    {
        $this->handler = static function (RequestInterface $request) use ($response): ResponseInterface {
            return $response;
        };

        return $this;
    }

    public function withHandler(Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            $this->handler,
        );
    }
}
