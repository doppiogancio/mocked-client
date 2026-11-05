<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function is_array;
use function json_decode;

/** One request the code under test actually made, with what it got back. */
final class RecordedCall
{
    public function __construct(
        public readonly RequestInterface $request,
        public readonly ResponseInterface|null $response = null,
        public readonly string|null $matchedStub = null,
    ) {
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function path(): string
    {
        return $this->request->getUri()->getPath();
    }

    public function body(): string
    {
        $stream = $this->request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    /** @return array<array-key, mixed> the decoded JSON body, empty when the body is not a JSON structure */
    public function jsonBody(): array
    {
        $decoded = json_decode($this->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function wasMatched(): bool
    {
        return $this->matchedStub !== null;
    }
}
