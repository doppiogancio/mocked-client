<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Route
{
    private string $method;
    private string $path;

    /** @var callable(RequestInterface, array): ResponseInterface */
    private $handler;

    /**
     * @param callable(RequestInterface, array): ResponseInterface $handler
     */
    public function __construct(string $method, string $path, callable $handler)
    {
        $this->method  = $method;
        $this->path    = $path;
        $this->handler = $handler;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }
}
