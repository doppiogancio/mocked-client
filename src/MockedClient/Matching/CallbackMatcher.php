<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Closure;
use Psr\Http\Message\RequestInterface;

use function sprintf;

final class CallbackMatcher implements RequestMatcher
{
    /** @var Closure(RequestInterface):bool */
    private readonly Closure $callback;

    /** @param callable(RequestInterface):bool $callback */
    public function __construct(callable $callback, private readonly string $description = 'callback')
    {
        $this->callback = Closure::fromCallable($callback);
    }

    public function mismatch(RequestInterface $request): string|null
    {
        return ($this->callback)($request) ? null : sprintf('%s returned false', $this->description);
    }
}
