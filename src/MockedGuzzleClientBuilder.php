<?php

declare(strict_types=1);

namespace MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class MockedGuzzleClientBuilder
{
    private HandlerBuilder $handlerBuilder;

    public function __construct(HandlerBuilder $handlerBuilder)
    {
        $this->handlerBuilder = $handlerBuilder;
    }

    public function buildGuzzleClient(): Client
    {
        return new Client(['handler' => new HandlerStack($this->handlerBuilder->build())]);
    }
}
