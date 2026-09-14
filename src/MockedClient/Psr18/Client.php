<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Psr18;

use DoppioGancio\MockedClient\RequestHandler;
use DoppioGancio\MockedClient\Route\Route;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 mocked client with no dependency on Guzzle: useful with any
 * PSR-18 consumer (e.g. php-http/discovery, Symfony's Psr18Client).
 */
class Client implements ClientInterface
{
    public function __construct(private readonly RequestHandler $requestHandler)
    {
    }

    public function addRoute(Route $route): self
    {
        $this->requestHandler->addRoute($route);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->requestHandler->handle($request);
    }
}
