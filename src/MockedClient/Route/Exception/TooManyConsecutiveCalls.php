<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route\Exception;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function sprintf;

class TooManyConsecutiveCalls extends Exception
{
    /** @param ResponseInterface[] $responses */
    public function __construct(
        RequestInterface $request,
        public readonly array $responses,
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        $message = sprintf('Endpoint "%s" has been called too many times', $request->getUri()->getPath());

        parent::__construct($message, $code, $previous);
    }
}
