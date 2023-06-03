<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Closure;
use DoppioGancio\MockedClient\Exception\RouteNotFound;
use DoppioGancio\MockedClient\Route\Route;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function ltrim;
use function sprintf;

class HandlerBuilder
{
    /** @var Route[] */
    private array $routes = [];

    public function __construct(
        private readonly ServerRequestFactoryInterface $serverRequestFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function addRoute(Route $route): self
    {
        $this->routes[] = $route;

        return $this;
    }

    public function build(): Closure
    {
        return function (RequestInterface $request): PromiseInterface {
            $router = new Router();
            foreach ($this->routes as $route) {
                $router->map(
                    $route->method,
                    $route->path,
                    $route->handler,
                );
            }

            $this->logger->debug(
                sprintf('Request: %s %s', $request->getMethod(), $request->getUri()),
                ['request' => $request],
            );

            $serverRequest = $this->serverRequestFactory
                ->createServerRequest(
                    $request->getMethod(),
                    sprintf('/%s', ltrim($request->getUri()->__toString(), '/')),
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
                        $request->getUri(),
                    ),
                    [
                        'request' => $request,
                        'response' => $response,
                    ],
                );

                return new FulfilledPromise($response);
            } catch (NotFoundException $e) {
                $this->logError($e, $request);

                throw new RouteNotFound(
                    $request->getMethod(),
                    $request->getUri()->getPath(),
                    $this->routes,
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
