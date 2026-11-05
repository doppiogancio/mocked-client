<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function sprintf;

/** A MockResponse::sequence() ran out of responses. */
class TooManyCalls extends RuntimeException implements MockedClientException, RequestExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, int $available)
    {
        parent::__construct(sprintf(
            '%s %s was called %d time(s), but its response sequence only defines %d.',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $available + 1,
            $available,
        ));
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
