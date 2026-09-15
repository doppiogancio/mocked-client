<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use DoppioGancio\MockedClient\Exception\IncompleteStub;
use DoppioGancio\MockedClient\Matching\CallbackMatcher;
use DoppioGancio\MockedClient\Matching\HeaderMatcher;
use DoppioGancio\MockedClient\Matching\JsonBodyMatcher;
use DoppioGancio\MockedClient\Matching\MethodPath;
use DoppioGancio\MockedClient\Matching\QueryMatcher;
use DoppioGancio\MockedClient\Matching\RequestMatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function sprintf;

/**
 * One matcher chain plus one response. Narrow it with the where*() methods,
 * then close it with reply(). Stubs are tried in registration order and the
 * first one that accepts the request wins, so the catch all goes last.
 */
final class Stub
{
    /** @var RequestMatcher[] */
    private array $matchers;

    private MockResponse|null $response = null;
    private int $calls                  = 0;

    public function __construct(private readonly MethodPath $methodPath)
    {
        $this->matchers = [$methodPath];
    }

    /** @param array<array-key, mixed>|string $query either ['code' => 'it'] or 'code=it&page=2' */
    public function whereQuery(array|string $query): self
    {
        return $this->where(new QueryMatcher($query));
    }

    /** Passing no value only requires the header to be present. */
    public function whereHeader(string $name, string|null $value = null): self
    {
        return $this->where(new HeaderMatcher($name, $value));
    }

    /** @param array<array-key, mixed> $subset the keys the JSON body must contain; extra keys are ignored */
    public function whereJson(array $subset): self
    {
        return $this->where(new JsonBodyMatcher($subset));
    }

    /** @param callable(RequestInterface):bool $callback */
    public function whereCallback(callable $callback, string $description = 'callback'): self
    {
        return $this->where(new CallbackMatcher($callback, $description));
    }

    public function where(RequestMatcher $matcher): self
    {
        $this->matchers[] = $matcher;

        return $this;
    }

    public function reply(MockResponse|ResponseInterface $response): self
    {
        $this->response = $response instanceof ResponseInterface
            ? MockResponse::psr7($response)
            : $response;

        return $this;
    }

    public function label(): string
    {
        return sprintf('%s %s', $this->methodPath->method(), $this->methodPath->pattern()->path());
    }

    public function methodPath(): MethodPath
    {
        return $this->methodPath;
    }

    public function calls(): int
    {
        return $this->calls;
    }

    /** @return string|null null when the stub accepts the request, otherwise why it does not */
    public function mismatch(RequestInterface $request): string|null
    {
        foreach ($this->matchers as $matcher) {
            $reason = $matcher->mismatch($request);
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @internal
     *
     * @throws IncompleteStub
     */
    public function respondTo(
        RequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): ResponseInterface {
        if ($this->response === null) {
            throw new IncompleteStub($this->methodPath->method(), $this->methodPath->pattern()->path());
        }

        $this->calls++;

        return $this->response->resolve($request, $responseFactory, $streamFactory);
    }
}
