<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MockedGuzzleClientBuilder
{
    private HandlerBuilder $handlerBuilder;

    /** @var array<callable>  */
    private array $middlewares = [];

    public function __construct(
        HandlerBuilder $handlerBuilder,
        ?LoggerInterface $logger = null
    ) {
        $this->handlerBuilder = $handlerBuilder;
        $this->addMiddleware(Middleware::log(
            $logger ?? new NullLogger(),
            new MessageFormatter()
        ));
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
