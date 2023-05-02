<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\ResponseInterface;

class ConditionalResponse
{
    /** @param array<int|string,mixed|string> $requiredParameters */
    public function __construct(
        private readonly array $requiredParameters,
        public readonly ResponseInterface $response,
    ) {
    }

    /** @param array<int|string,mixed|string> $parameters */
    public function matchAgainst(array $parameters): bool
    {
        foreach ($this->requiredParameters as $key => $value) {
            if (! isset($parameters[$key])) {
                return false;
            }

            if ($parameters[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
