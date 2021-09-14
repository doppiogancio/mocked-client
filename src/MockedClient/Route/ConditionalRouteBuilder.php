<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function parse_str;

class ConditionalRouteBuilder
{
    use Builder;

    private ResponseInterface $defaultNotFoundResponse;
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;

    /** @var ConditionalResponse[] */
    private array $responses = [];

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory         = $responseFactory;
        $this->streamFactory           = $streamFactory;
        $this->defaultNotFoundResponse = new Response(404);
    }

    public function withDefaultNotFoundResponse(ResponseInterface $response): self
    {
        $this->defaultNotFoundResponse = $response;

        return $this;
    }

    public function withConditionalResponse(string $queryString, ResponseInterface $response): self
    {
        parse_str($queryString, $parameters);
        $this->responses[] = new ConditionalResponse(
            $parameters,
            $response
        );

        return $this;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function withConditionalStringResponse(
        string $queryString,
        string $content,
        int $httpStatus = 200,
        array $headers = []
    ): self {
        return $this->withConditionalResponse(
            $queryString,
            $this->buildResponseFromString($content, $httpStatus, $headers)
        );
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function withConditionalFileResponse(
        string $queryString,
        string $file,
        int $httpStatus = 200,
        array $headers = []
    ): self {
        return $this->withConditionalResponse(
            $queryString,
            $this->buildResponseFromFile($file, $httpStatus, $headers)
        );
    }

    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            $this->buildHandler()
        );
    }

    private function buildHandler(): callable
    {
        return function (RequestInterface $request): ResponseInterface {
            parse_str($request->getUri()->getQuery(), $requestParameters);
            foreach ($this->responses as $conditionalResponse) {
                if ($conditionalResponse->matchAgainst($requestParameters)) {
                    return $conditionalResponse->getResponse();
                }
            }

            return $this->defaultNotFoundResponse;
        };
    }
}
