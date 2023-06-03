<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class MockedGuzzleClientBuilder
{
    /** @param array<callable> $middlewares */
    public function __construct(
        private readonly HandlerBuilder $handlerBuilder,
        private array $middlewares = [],
    ) {
    }

    public function addMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /** @param array<string,mixed> $options */
    public function build(array $options = []): Client
    {
        $handler = $this->handlerBuilder->build();

        $handlerStack = HandlerStack::create($handler);

        foreach ($this->middlewares as $middleware) {
            $handlerStack->push($middleware);
        }

        $options['handler'] = $handlerStack;

        return new Client($options);
    }
}
