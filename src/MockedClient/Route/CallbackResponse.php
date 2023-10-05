<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\ResponseInterface;

class CallbackResponse
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback, private ResponseInterface $response)
    {
        $this->callback = $callback;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
