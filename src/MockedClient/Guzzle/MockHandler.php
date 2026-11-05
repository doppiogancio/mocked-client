<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Guzzle;

use Closure;
use DoppioGancio\MockedClient\MockServer;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Adapts the MockServer to the Guzzle handler contract: a callable taking a
 * request and returning a promise. Failures come back as a rejected promise so
 * that middlewares see them the way they would see a real transport error.
 */
final class MockHandler
{
    public function __construct(private readonly MockServer $server)
    {
    }

    public function build(): Closure
    {
        return function (RequestInterface $request): PromiseInterface {
            try {
                return Create::promiseFor($this->server->handle($request));
            } catch (Throwable $e) {
                return Create::rejectionFor($e);
            }
        };
    }
}
