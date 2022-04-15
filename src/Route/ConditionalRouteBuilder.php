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

    private ResponseInterface $defaultResponse;
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;

    /** @var ConditionalResponse[] */
    private array $responses = [];

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
        $this->defaultResponse = new Response(404);
    }

    public function withDefaultResponse(ResponseInterface $response): self
    {
        $this->defaultResponse = $response;

        return $this;
    }

    /**
     * @param array<string,mixed> $headers
     *
     * @return $this
     */
    public function withDefaultStringResponse(string $content, int $httpStatus = 200, array $headers = []): self
    {
        $this->defaultResponse = $this->buildResponseFromString($content, $httpStatus, $headers);

        return $this;
    }

    /**
     * @param array<string,mixed> $headers
     *
     * @return $this
     */
    public function withDefaultFileResponse(string $file, int $httpStatus = 200, array $headers = []): self
    {
        $this->defaultResponse = $this->buildResponseFromFile($file, $httpStatus, $headers);

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
     * @param array<string, mixed> $headers
     *
     * @return ConditionalRouteBuilder
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
     * @param array<string, mixed> $headers
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

            // TODO we could add a possible default response, before the not found response

            return $this->defaultResponse;
        };
    }
}
