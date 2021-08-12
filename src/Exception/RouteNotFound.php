<?php

namespace MockedClient\Exception;

use Exception;

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
