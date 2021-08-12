<?php

namespace MockedClient;

use GuzzleHttp\Client;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use MockedClient\Exception\RouteNotFound;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class HandlerStackBuilder
{
    /**
     * @var Route[]
     */
    private array $_routes = [];
    private bool $_debug = false;

    public function debug(): self
    {
        $this->_debug = true;
        return $this;
    }

    public function addRoute(string $method, string $path, callable $handler): self
    {
        $this->_routes[] = new Route($method, $path, $handler);

        return $this;
    }

    public function addRouteWithResponse(
        string $method,
        string $path,
        ResponseInterface $response
    ): self {
        $this->addRoute(
            $method,
            $path,
            static function () use ($response): ResponseInterface {
                return $response;
            }
        );

        return $this;
    }

    public function addRouteWithString(
        string $method,
        string $path,
        string $responseContent,
        int $httpStatus = 200,
        array $headers = []
    ): self {
        $this->addRoute(
            $method,
            $path,
            static function () use (
                $httpStatus,
                $headers,
                $responseContent
            ): ResponseInterface {
                return new Response(
                    $httpStatus,
                    $headers,
                    $responseContent
                );
            }
        );

        return $this;
    }

    public function addRouteWithFile(
        string $method,
        string $path,
        string $file,
        int $httpStatus = 200,
        array $headers = []
    ): self {
        $this->addRouteWithString(
            $method,
            $path,
            (string) file_get_contents($file),
            $httpStatus,
            $headers
        );

        return $this;
    }

    public function build(): HandlerStack
    {
        return new HandlerStack(
            function (RequestInterface $request) {
                $router = new Router();
                foreach ($this->_routes as $route) {
                    $router->map(
                        $route->getMethod(),
                        $route->getPath(),
                        $route->getHandler()
                    );
                }

                if ($this->_debug) {
                    print sprintf(
                        "%s - %s %s\n",
                        date(\DateTime::W3C),
                        $request->getMethod(),
                        $request->getUri()
                    );
                }

                $serverRequest = new ServerRequest(
                    $request->getMethod(),
                    $request->getUri()
                );

                try {
                    return $router->dispatch($serverRequest);
                } catch (NotFoundException $e) {
                    throw new RouteNotFound(
                        $request->getMethod(),
                        $request->getUri()->getPath()
                    );
                }
            }
        );
    }

    public function buildGuzzleClient(): Client
    {
        return new Client(['handler' => $this->build()]);
    }
}
