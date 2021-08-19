<?php

declare(strict_types=1);

namespace MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

class MockedGuzzleClientBuilder
{
    private HandlerBuilder $handlerBuilder;

    public function __construct(HandlerBuilder $handlerBuilder)
    {
        $this->handlerBuilder = $handlerBuilder;
    }

    public function build(): Client
    {
        $handler = $this->handlerBuilder->build();

        return new Client([
            'handler' => new HandlerStack(static function (RequestInterface $request, $options) use ($handler): PromiseInterface {
                return new FulfilledPromise($handler($request));
            }),
        ]);
    }
}
