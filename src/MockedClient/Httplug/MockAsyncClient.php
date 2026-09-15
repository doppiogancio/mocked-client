<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Httplug;

use DoppioGancio\MockedClient\MockServer;
use Http\Client\HttpAsyncClient;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/** Mocks an HTTPlug asynchronous client. Also usable synchronously via sendRequest(). */
final class MockAsyncClient implements HttpAsyncClient
{
    public function __construct(private readonly MockServer $server)
    {
    }

    public function sendAsyncRequest(RequestInterface $request): Promise
    {
        try {
            return new FulfilledPromise($this->server->handle($request));
        } catch (Throwable $e) {
            return new RejectedPromise($e);
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->server->handle($request);
    }
}
