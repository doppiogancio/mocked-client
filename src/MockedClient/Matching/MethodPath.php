<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Psr\Http\Message\RequestInterface;

use function sprintf;
use function strtoupper;

final class MethodPath implements RequestMatcher
{
    private readonly string $method;
    private readonly PathPattern $pattern;

    public function __construct(string $method, string $path)
    {
        $this->method  = strtoupper($method);
        $this->pattern = new PathPattern($path);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pattern(): PathPattern
    {
        return $this->pattern;
    }

    public function mismatch(RequestInterface $request): string|null
    {
        if (strtoupper($request->getMethod()) !== $this->method) {
            return sprintf('method is %s, not %s', $this->method, strtoupper($request->getMethod()));
        }

        if (! $this->pattern->matches($request->getUri()->getPath())) {
            return sprintf('path does not match "%s"', $this->pattern->path());
        }

        return null;
    }
}
