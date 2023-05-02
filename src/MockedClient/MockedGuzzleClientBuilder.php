<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

use function assert;

class MockedGuzzleClientBuilder
{
    public function __construct(
        private readonly HandlerBuilder $handlerBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(): Client
    {
        $handler = $this->handlerBuilder->build();

        $callback = static function (RequestInterface $request) use ($handler): PromiseInterface {
            $response = $handler($request);
            assert($response instanceof Response);
            if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
                throw new ClientException(
                    $response->getBody()->getContents(),
                    $request,
                    $response,
                );
            }

            if ($response->getStatusCode() >= 500 && $response->getStatusCode() < 600) {
                throw new ServerException(
                    $response->getBody()->getContents(),
                    $request,
                    $response,
                );
            }

            return new FulfilledPromise($response);
        };

        $handlerStack = new HandlerStack($callback);

        $handlerStack->push(Middleware::log(
            $this->logger,
            new MessageFormatter(),
        ));

        return new Client(['handler' => $handlerStack]);
    }
}
