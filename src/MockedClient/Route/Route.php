<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Closure;

class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly Closure $handler,
    ) {
    }

    public function getHandler(): Closure
    {
        return $this->handler;
    }
}
