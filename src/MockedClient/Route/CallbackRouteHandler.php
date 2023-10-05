<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use DoppioGancio\MockedClient\Route\Exception\ResponseNotFound;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CallbackRouteHandler
{
    /** @param array<array{'callback': callable(RequestInterface $request):bool, 'response': ResponseInterface}> $responses */
    public function __construct(private readonly array $responses)
    {
    }

    public function __invoke(RequestInterface $request): ResponseInterface
    {
        foreach ($this->responses as $couple) {
            if ($couple['callback']($request)) {
                return $couple['response'];
            }
        }

        throw new ResponseNotFound();
    }
}
