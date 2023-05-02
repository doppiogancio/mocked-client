<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Closure;
use DoppioGancio\MockedClient\Route\Exception\IncompleteRoute;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function parse_str;

class ConditionalRouteBuilder extends Builder
{
    private ResponseInterface|null $defaultResponse = null;

    /** @var ConditionalResponse[] */
    private array $responses = [];

    public function new(): ConditionalRouteBuilder
    {
        return new ConditionalRouteBuilder($this->responseFactory, $this->streamFactory);
    }

    public function withDefaultResponse(ResponseInterface $response): self
    {
        $this->defaultResponse = $response;

        return $this;
    }

    /** @param array<string, mixed> $headers */
    public function withDefaultStringResponse(string $queryString, int $httpStatus = 200, array $headers = []): self
    {
        return $this->withDefaultResponse(
            $this->buildResponseFromString($queryString, $httpStatus, $headers),
        );
    }

    /** @param array<string, mixed> $headers */
    public function withDefaultFileResponse(string $file, int $httpStatus = 200, array $headers = []): self
    {
        return $this->withDefaultResponse(
            $this->buildResponseFromFile($file, $httpStatus, $headers),
        );
    }

    public function withConditionalResponse(string $queryString, ResponseInterface $response): self
    {
        parse_str($queryString, $parameters);
        $this->responses[] = new ConditionalResponse(
            $parameters,
            $response,
        );

        return $this;
    }

    /** @param array<string, mixed> $headers */
    public function withConditionalStringResponse(
        string $queryString,
        string $content,
        int $httpStatus = 200,
        array $headers = [],
    ): self {
        return $this->withConditionalResponse(
            $queryString,
            $this->buildResponseFromString($content, $httpStatus, $headers),
        );
    }

    /** @param array<string, mixed> $headers */
    public function withConditionalFileResponse(
        string $queryString,
        string $file,
        int $httpStatus = 200,
        array $headers = [],
    ): self {
        return $this->withConditionalResponse(
            $queryString,
            $this->buildResponseFromFile($file, $httpStatus, $headers),
        );
    }

    /** @throws IncompleteRoute */
    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            $this->buildHandler(),
        );
    }

    private function buildHandler(): Closure
    {
        return function (RequestInterface $request): ResponseInterface {
            parse_str($request->getUri()->getQuery(), $requestParameters);
            foreach ($this->responses as $conditionalResponse) {
                if ($conditionalResponse->matchAgainst($requestParameters)) {
                    return $conditionalResponse->response;
                }
            }

            return $this->defaultResponse ?? new Response(404);
        };
    }
}
