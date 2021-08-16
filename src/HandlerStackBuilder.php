<?php

declare(strict_types=1);

namespace MockedClient;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use MockedClient\Exception\RouteNotFound;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function date;
use function file_get_contents;
use function sprintf;

class HandlerStackBuilder
{
    /** @var Route[] */
    private array $routes = [];
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function addRoute(string $method, string $path, callable $handler): self
    {
        $this->routes[] = new Route($method, $path, $handler);

        return $this;
    }

    public function addRouteWithResponse(string $method, string $path, ResponseInterface $response): self
    {
        $this->addRoute(
            $method,
            $path,
            static function () use ($response): ResponseInterface {
                return $response;
            }
        );

        return $this;
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @return $this
     */
    public function addRouteWithString(
        string $method,
        string $path,
        string $responseContent,
        int $httpStatus = 200,
        array $headers = []
    ): self {
        $this->addRouteWithResponse(
            $method,
            $path,
            new Response(
                $httpStatus,
                $headers,
                $responseContent
            )
        );

        return $this;
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @return $this
     */
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
            function (RequestInterface $request): ResponseInterface {
                $router = new Router();
                foreach ($this->routes as $route) {
                    $router->map(
                        $route->getMethod(),
                        $route->getPath(),
                        $route->getHandler()
                    );
                }

                if ($this->logger) {
                    $this->logger->debug(
                        sprintf(
                            "%s - %s %s\n",
                            date(DateTime::W3C),
                            $request->getMethod(),
                            $request->getUri()
                        )
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
