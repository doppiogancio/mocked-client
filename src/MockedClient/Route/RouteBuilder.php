<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class RouteBuilder
{
    use Builder;

    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;

    protected ?string $method = null;
    protected ?string $path   = null;

    /** @var ?callable null */
    protected $handler = null;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function withStringResponse(string $content, int $httpStatus = 200, array $headers = []): self
    {
        $response = $this->buildResponseFromString($content, $httpStatus, $headers);

        return $this->withResponse($response);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
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

    public function withHandler(callable $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            $this->handler
        );
    }
}
