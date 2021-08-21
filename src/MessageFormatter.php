<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DateTime;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function date;
use function sprintf;

class MessageFormatter implements MessageFormatterInterface
{
    public function formatRequest(RequestInterface $request): string
    {
        return sprintf(
            "%s - %s %s\n",
            date(DateTime::W3C),
            $request->getMethod(),
            $request->getUri()
        );
    }

    public function formatResponse(ResponseInterface $response): string
    {
        return sprintf(
            "%s - %d %s\n",
            date(DateTime::W3C),
            $response->getStatusCode(),
            (string) $response->getBody()
        );
    }

    public function formatException(Throwable $exception): string
    {
        return sprintf(
            "%s - %s %s\n",
            date(DateTime::W3C),
            $exception::class,
            $exception->getMessage()
        );
    }
}
