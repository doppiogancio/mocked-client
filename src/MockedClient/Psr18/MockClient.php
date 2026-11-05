<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Psr18;

use DoppioGancio\MockedClient\MockServer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client with no third party dependency, for any code that depends on
 * Psr\Http\Client\ClientInterface. Every exception it throws implements
 * ClientExceptionInterface, as PSR-18 requires.
 */
final class MockClient implements ClientInterface
{
    public function __construct(private readonly MockServer $server)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->server->handle($request);
    }
}
