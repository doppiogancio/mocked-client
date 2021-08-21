<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\MessageFormatterInterface;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class MockedGuzzleClientBuilder
{
    private HandlerBuilder $handlerBuilder;
    private ?LoggerInterface $logger;
    private MessageFormatterInterface $messageFormatter;

    public function __construct(
        HandlerBuilder $handlerBuilder,
        ?LoggerInterface $logger = null,
        ?MessageFormatterInterface $messageFormatter = null
    ) {
        $this->handlerBuilder = $handlerBuilder;
        $this->logger         = $logger;

        $this->messageFormatter = new MessageFormatter(
            '{method} {uri} HTTP/{version} HEADERS: {req_headers} ' .
            'Payload: {req_body} RESPONSE: STATUS: {code} BODY: {res_body}'
        );

        if ($messageFormatter === null) {
            return;
        }

        $this->messageFormatter = $messageFormatter;
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
                $this->messageFormatter,
            ));
        }

        return new Client(['handler' => $handlerStack]);
    }
}
