<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown while defining a stub, not while answering a request: it points at
 * the line where the unreadable fixture was passed to MockResponse::file().
 */
class FileNotFound extends InvalidArgumentException implements MockedClientException
{
    public function __construct(string $file)
    {
        parent::__construct(sprintf('Fixture file not readable: %s', $file));
    }
}
