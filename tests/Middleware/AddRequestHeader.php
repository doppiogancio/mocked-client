<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Middleware;

use Psr\Http\Message\RequestInterface;

class AddRequestHeader
{
    private string $header;
    private string $value;

    public function __construct(string $header, string $value)
    {
        $this->header = $header;
        $this->value  = $value;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $request = $request->withHeader($this->header, $this->value);

            return $handler($request, $options);
        };
    }
}
