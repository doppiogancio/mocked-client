<?php

namespace MockedClient;

class Route
{
    private string $_method;
    private string $_method2;
    private string $_path;
    /**
     * @var callable
     */
    private $_handler;

    public function __construct(string $method, string $path, callable $handler)
    {
        $this->_method = $method;
        $this->_path = $path;
        $this->_handler = $handler;
    }

    public function getMethod(): string
    {
        return $this->_method;
    }

    public function getPath(): string
    {
        return $this->_path;
    }

    public function getHandler(): callable
    {
        return $this->_handler;
    }
}
