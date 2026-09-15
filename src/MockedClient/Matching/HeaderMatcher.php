<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Psr\Http\Message\RequestInterface;

use function sprintf;

final class HeaderMatcher implements RequestMatcher
{
    public function __construct(
        private readonly string $name,
        private readonly string|null $value = null,
    ) {
    }

    public function mismatch(RequestInterface $request): string|null
    {
        if (! $request->hasHeader($this->name)) {
            return sprintf('header "%s" is absent', $this->name);
        }

        if ($this->value !== null && $request->getHeaderLine($this->name) !== $this->value) {
            return sprintf(
                'header "%s" is "%s", not "%s"',
                $this->name,
                $request->getHeaderLine($this->name),
                $this->value,
            );
        }

        return null;
    }
}
