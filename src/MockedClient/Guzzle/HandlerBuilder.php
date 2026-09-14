<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Guzzle;

use Closure;
use DoppioGancio\MockedClient\RequestHandler;
use DoppioGancio\MockedClient\Route\Route;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Adapts the framework agnostic RequestHandler to the Guzzle HandlerStack
 * contract: a Closure taking a Request and returning a fulfilled Promise.
 */
class HandlerBuilder
{
    public function __construct(private readonly RequestHandler $requestHandler)
    {
    }

    public function addRoute(Route $route): self
    {
        $this->requestHandler->addRoute($route);

        return $this;
    }

    public function build(): Closure
    {
        return function (RequestInterface $request): PromiseInterface {
            return new FulfilledPromise($this->requestHandler->handle($request));
        };
    }
}
