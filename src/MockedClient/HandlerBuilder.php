<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DoppioGancio\MockedClient\Exception\RouteNotFound;
use DoppioGancio\MockedClient\Route\Route;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function ltrim;
use function sprintf;

class HandlerBuilder
{
    /** @var Route[] */
    private array $routes = [];
    private ServerRequestFactoryInterface $serverRequestFactory;
    private LoggerInterface $logger;

    public function __construct(ServerRequestFactoryInterface $serverRequestFactory, ?LoggerInterface $logger = null)
    {
        $this->serverRequestFactory = $serverRequestFactory;
        $this->logger               = $logger ?? new NullLogger();
    }

    public function addRoute(Route $route): self
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @return callable(RequestInterface): ResponseInterface
     */
    public function build(): callable
    {
        return function (RequestInterface $request): ResponseInterface {
            $uri = $request->getUri()
                ->withScheme('')
                ->withHost('')
                ->withPort(null)
                ->withUserInfo('');

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
                ->createServerRequest(
                    $request->getMethod(),
                    sprintf('/%s', ltrim($uri->__toString(), '/'))
                );

            $serverRequest = $serverRequest->withBody($request->getBody());
            foreach ($request->getHeaders() as $name => $value) {
                $serverRequest = $serverRequest->withHeader($name, $value);
            }

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
