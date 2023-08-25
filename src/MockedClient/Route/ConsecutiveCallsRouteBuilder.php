<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\ResponseInterface;

class ConsecutiveCallsRouteBuilder extends RouteBuilder
{
    /** @var ResponseInterface[]  */
    private array $responses = [];

    public function new(): self
    {
        return new self($this->responseFactory, $this->streamFactory);
    }

    public function withResponse(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function build(): Route
    {
        return $this->buildRoute(
            $this->method,
            $this->path,
            (new ConsecutiveCallsRouteHandler($this->responses))(...),
        );
    }
}
