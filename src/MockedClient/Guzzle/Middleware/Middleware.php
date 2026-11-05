<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Guzzle\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/** Base class for the Guzzle middlewares you push onto MockServer::guzzle(). */
class Middleware
{
    protected RequestInterface $request;

    public function __invoke(callable $handler): callable
    {
        /** @param array<string, mixed> $options */
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $this->request = $this->mapRequest($request);

            return $this->mapResponse($handler($this->request, $options));
        };
    }

    protected function mapRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    protected function mapResponse(PromiseInterface $response): PromiseInterface
    {
        return $response;
    }
}
