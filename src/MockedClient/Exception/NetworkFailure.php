<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * The simulated transport failure produced by MockResponse::failure(), for
 * exercising retry, timeout and circuit breaker code paths.
 */
class NetworkFailure extends RuntimeException implements MockedClientException, NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
