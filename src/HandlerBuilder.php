<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DoppioGancio\MockedClient\Exception\RouteNotFound;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function assert;
use function fopen;
use function is_resource;
use function sprintf;

class HandlerBuilder
{
    /** @var Route[] */
    private array $routes = [];

    private LoggerInterface $logger;

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
        $this->logger               = $logger ?? new NullLogger();
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
     * @param array<string,mixed> $headers
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
     * @template T of MessageInterface
     */
    private function addHeaders(MessageInterface $message, array $headers): MessageInterface
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
        $fp = fopen($file, 'rb');
        assert(is_resource($fp));
        $response = $this->responseFactory
            ->createResponse($httpStatus)
            ->withBody($this->streamFactory->createStreamFromResource($fp));

        $response = $this->addHeaders($response, $headers);
        $this->addRouteWithResponse($method, $path, $response);

        return $this;
    }

    /**
     * @return callable(RequestInterface): ResponseInterface
     */
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

            $this->logger->debug(
                sprintf('Request: %s %s', $request->getMethod(), $request->getUri()),
                ['request' => $request]
            );

            $serverRequest = $this->serverRequestFactory
                ->createServerRequest($request->getMethod(), $request->getUri());

            try {
                $response = $router->dispatch($serverRequest);
                $this->logger->debug(
                    sprintf(
                        'Response: %d %s %s',
                        $response->getStatusCode(),
                        $request->getMethod(),
                        $request->getUri()
                    ),
                    [
                        'request' => $request,
                        'response' => $response,
                    ]
                );

                return $response;
            } catch (NotFoundException $e) {
                $this->logError($e, $request);

                throw new RouteNotFound(
                    $request->getMethod(),
                    $request->getUri()->getPath(),
                    $this->routes
                );
            } catch (Throwable $e) {
                $this->logError($e, $request);

                throw $e;
            }
        };
    }

    private function logError(Throwable $e, RequestInterface $request): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e,
            'request' => $request,
        ]);
    }
}
