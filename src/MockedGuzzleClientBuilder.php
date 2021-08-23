<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MockedGuzzleClientBuilder
{
    private HandlerBuilder $handlerBuilder;
    private LoggerInterface $logger;

    public function __construct(
        HandlerBuilder $handlerBuilder,
        ?LoggerInterface $logger = null
    ) {
        $this->handlerBuilder = $handlerBuilder;
        $this->logger         = $logger ?? new NullLogger();
    }

    public function build(): Client
    {
        $handler = $this->handlerBuilder->build();

        $callback = static function (RequestInterface $request, $options) use ($handler): PromiseInterface {
            return new FulfilledPromise($handler($request));
        };

        $handlerStack = new HandlerStack($callback);

        if ($this->logger !== null) {
            $handlerStack->push(Middleware::log(
                $this->logger,
                new MessageFormatter()
            ));
        }

        return new Client(['handler' => $handlerStack]);
    }
}
