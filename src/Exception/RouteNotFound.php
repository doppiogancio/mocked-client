<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use DoppioGancio\MockedClient\Route;
use Exception;

use function sprintf;

class RouteNotFound extends Exception
{
    /** @var Route[] */
    private array $routes;

    /**
     * @param Route[] $routes
     */
    public function __construct(string $method, string $path, array $routes = [])
    {
        $this->routes = $routes;
        $message      = sprintf(
            "Mocked route %s %s not found\n\n%s",
            $method,
            $path,
            $this->routesToString($routes)
        );

        parent::__construct($message);
    }

    /**
     * @param Route[] $routes
     */
    private function routesToString(array $routes): string
    {
        $string = "Mocked routes:\n\n";

        foreach ($routes as $route) {
            $string .= sprintf("ROUTE %s %s\n", $route->getMethod(), $route->getPath());
        }

        return $string;
    }

    public function getRoutesAsString(): string
    {
        return $this->routesToString($this->routes);
    }
}
