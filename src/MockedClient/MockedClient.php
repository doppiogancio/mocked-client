<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DoppioGancio\MockedClient\Guzzle\ClientBuilder as GuzzleClientBuilder;
use DoppioGancio\MockedClient\Guzzle\HandlerBuilder as GuzzleHandlerBuilder;
use DoppioGancio\MockedClient\Psr18\Client as Psr18Client;
use DoppioGancio\MockedClient\Route\Route;
use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Entry point for mocking a client: define routes once with get()/post()/...,
 * then get either a mocked Guzzle client or a plain PSR-18 client out of it.
 */
final class MockedClient
{
    private readonly RequestHandler $requestHandler;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        ServerRequestFactoryInterface|null $serverRequestFactory = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->requestHandler = new RequestHandler(
            $serverRequestFactory ?? Psr17FactoryDiscovery::findServerRequestFactory(),
            $logger ?? new NullLogger(),
        );
    }

    /** Discovers the PSR-17 factories via php-http/discovery; use the constructor directly to provide your own. */
    public static function create(LoggerInterface|null $logger = null): self
    {
        return new self(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            logger: $logger,
        );
    }

    public function get(string $path): RouteExpectation
    {
        return $this->on('GET', $path);
    }

    public function post(string $path): RouteExpectation
    {
        return $this->on('POST', $path);
    }

    public function put(string $path): RouteExpectation
    {
        return $this->on('PUT', $path);
    }

    public function patch(string $path): RouteExpectation
    {
        return $this->on('PATCH', $path);
    }

    public function delete(string $path): RouteExpectation
    {
        return $this->on('DELETE', $path);
    }

    public function on(string $method, string $path): RouteExpectation
    {
        $expectation = new RouteExpectation($this->responseFactory, $this->streamFactory);

        $this->requestHandler->addRoute(new Route($method, $path, $expectation->handle(...)));

        return $expectation;
    }

    /** Advanced: the framework-agnostic core, for embedding in your own PSR-7/PSR-18 wiring. */
    public function requestHandler(): RequestHandler
    {
        return $this->requestHandler;
    }

    /**
     * @param array<callable>     $middlewares
     * @param array<string,mixed> $options
     */
    public function guzzleClient(array $middlewares = [], array $options = []): GuzzleClient
    {
        $clientBuilder = new GuzzleClientBuilder(new GuzzleHandlerBuilder($this->requestHandler), $middlewares);

        return $clientBuilder->build($options);
    }

    public function psr18Client(): Psr18Client
    {
        return new Psr18Client($this->requestHandler);
    }
}
