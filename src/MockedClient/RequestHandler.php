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
use Throwable;

use function ltrim;
use function sprintf;

/**
 * Framework/client agnostic core: turns a PSR-7 request into the PSR-7
 * response of the matching mocked route. No dependency on Guzzle.
 */
class RequestHandler
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

    /** @throws RouteNotFound */
    public function handle(RequestInterface $request): ResponseInterface
    {
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
            ->createServerRequest($request->getMethod(), $this->relativeUri($request));

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

            return $response;
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
    }

    /** Avoid that a random host will cause routing issues */
    private function relativeUri(RequestInterface $request): string
    {
        $uri = $request->getUri();

        $relativeUri = sprintf('/%s', ltrim($uri->getPath(), '/'));
        if ($uri->getQuery() !== '') {
            $relativeUri .= '?' . $uri->getQuery();
        }

        if ($uri->getFragment() !== '') {
            $relativeUri .= '#' . $uri->getFragment();
        }

        return $relativeUri;
    }

    private function logError(Throwable $e, RequestInterface $request): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e,
            'request' => $request,
        ]);
    }
}
