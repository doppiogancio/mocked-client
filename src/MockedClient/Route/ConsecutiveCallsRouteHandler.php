<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use DoppioGancio\MockedClient\Route\Exception\TooManyConsecutiveCalls;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function count;

class ConsecutiveCallsRouteHandler
{
    private int $currentResponse = 0;

    /** @param ResponseInterface[] $responses */
    public function __construct(private readonly array $responses)
    {
    }

    public function __invoke(RequestInterface $request): ResponseInterface
    {
        if ($this->currentResponse === count($this->responses)) {
            throw new TooManyConsecutiveCalls($request, $this->responses);
        }

        return $this->responses[$this->currentResponse++];
    }
}
