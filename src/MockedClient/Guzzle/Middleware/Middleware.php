<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Guzzle\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

class Middleware
{
    protected RequestInterface $request;

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $this->request = $this->mapRequest($request);

            $response = $handler($this->request, $options);

            return $this->mapResponse($response);
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
