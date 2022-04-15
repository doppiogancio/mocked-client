<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\ResponseInterface;

class ConditionalResponse
{
    /** @var array<string,string> */
    private array $requiredParameters;
    private ResponseInterface $response;

    /**
     * @param array<string,string> $requiredParameters
     */
    public function __construct(array $requiredParameters, ResponseInterface $response)
    {
        $this->requiredParameters = $requiredParameters;
        $this->response           = $response;
    }

    /**
     * @param array<string,string> $parameters
     */
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

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
