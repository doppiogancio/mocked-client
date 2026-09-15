<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DoppioGancio\MockedClient\Matching\PathPattern;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function count;
use function strtoupper;

/** Everything the code under test asked for, in order. */
final class Journal
{
    /** @var RecordedCall[] */
    private array $calls = [];

    public function record(
        RequestInterface $request,
        ResponseInterface|null $response = null,
        string|null $matchedStub = null,
    ): void {
        $this->calls[] = new RecordedCall($request, $response, $matchedStub);
    }

    /**
     * @param string|null $path a literal path or a pattern such as "/country/{code}"
     *
     * @return RecordedCall[]
     */
    public function calls(string|null $method = null, string|null $path = null): array
    {
        $pattern = $path === null ? null : new PathPattern($path);

        $matching = [];
        foreach ($this->calls as $call) {
            if ($method !== null && strtoupper($call->method()) !== strtoupper($method)) {
                continue;
            }

            if ($pattern !== null && ! $pattern->matches($call->path())) {
                continue;
            }

            $matching[] = $call;
        }

        return $matching;
    }

    public function count(string|null $method = null, string|null $path = null): int
    {
        return count($this->calls($method, $path));
    }

    public function last(): RecordedCall|null
    {
        if ($this->calls === []) {
            return null;
        }

        return $this->calls[count($this->calls) - 1];
    }

    /** @return RecordedCall[] */
    public function unmatched(): array
    {
        $unmatched = [];
        foreach ($this->calls as $call) {
            if ($call->wasMatched()) {
                continue;
            }

            $unmatched[] = $call;
        }

        return $unmatched;
    }
}
