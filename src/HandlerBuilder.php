<?php

declare(strict_types=1);

namespace MockedClient;

use DateTime;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use MockedClient\Exception\RouteNotFound;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

use function date;
use function fopen;
use function sprintf;

class HandlerBuilder
{
    /** @var Route[] */
    private array $routes = [];

    private ?LoggerInterface $logger;

    private StreamFactoryInterface $streamFactory;

    private ResponseFactoryInterface $responseFactory;

    private ServerRequestFactoryInterface $serverRequestFactory;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->responseFactory      = $responseFactory;
        $this->streamFactory        = $streamFactory;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->logger               = $logger;
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
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStream($responseContent));

        $response = $this->addHeaders($response, $headers);
        $this->addRouteWithResponse($method, $path, $response);

        return $this;
    }

    /**
     * @param T                              $message
     * @param array<string, string|string[]> $headers
     *
     * @return T
     *
     * @template T
     */
    private function addHeaders($message, array $headers)
    {
        foreach ($headers as $header => $value) {
            $message = $message->withHeader($header, $value);
        }

        return $message;
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
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStreamFromResource(fopen($file, 'rb')));

        $response = $this->addHeaders($response, $headers);
        $this->addRouteWithResponse($method, $path, $response);

        return $this;
    }

    public function build(): callable
    {
        return function (RequestInterface $request): ResponseInterface {
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

            $serverRequest = $this->serverRequestFactory
                ->createServerRequest($request->getMethod(), $request->getUri());

            try {
                return $router->dispatch($serverRequest);
            } catch (NotFoundException $e) {
                throw new RouteNotFound(
                    $request->getMethod(),
                    $request->getUri()->getPath()
                );
            }
        };
    }
}
