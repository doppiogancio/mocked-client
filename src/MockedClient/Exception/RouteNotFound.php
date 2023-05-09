<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use DoppioGancio\MockedClient\Route\Route;
use Exception;

use function sprintf;

class RouteNotFound extends Exception
{
    /** @param Route[] $routes */
    public function __construct(string $method, string $path, array $routes = [])
    {
        $message = sprintf(
            "Mocked route %s %s not found \n%s",
            $method,
            $path,
            $this->routesToString($routes),
        );

        parent::__construct($message);
    }

    /** @param Route[] $routes */
    private function routesToString(array $routes): string
    {
        $string = "Mocked routes:\n\n";

        foreach ($routes as $route) {
            $string .= sprintf("ROUTE %s %s\n", $route->method, $route->path);
        }

        return $string;
    }
}
