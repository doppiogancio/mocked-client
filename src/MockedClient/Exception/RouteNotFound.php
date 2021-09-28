<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use Exception;

use function sprintf;

class RouteNotFound extends Exception
{
    public function __construct(string $method, string $path)
    {
        $message = sprintf(
            'Mocked route %s %s not found',
            $method,
            $path
        );

        parent::__construct($message);
    }
}
