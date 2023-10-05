<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use DoppioGancio\MockedClient\Route\Exception\ResponseNotFound;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackRouteHandler
{
    /** @param CallbackResponse[] $responses */
    public function __construct(private readonly array $responses)
    {
    }

    public function __invoke(RequestInterface $request): ResponseInterface
    {
        foreach ($this->responses as $couple) {
            if ($couple->getCallback()($request)) {
                return $couple->getResponse();
            }
        }

        throw new ResponseNotFound();
    }
}
