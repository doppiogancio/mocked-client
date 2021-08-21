<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

// phpcs:ignore
interface MessageFormatterInterface
{
    public function formatRequest(RequestInterface $request): string;

    public function formatResponse(ResponseInterface $response): string;

    public function formatException(Throwable $exception): string;
}
