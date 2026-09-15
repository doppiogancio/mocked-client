<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Psr\Http\Message\RequestInterface;

interface RequestMatcher
{
    /**
     * Returns null when the request matches, otherwise a short human readable
     * reason that ends up in the RequestNotMatched message.
     */
    public function mismatch(RequestInterface $request): string|null;
}
