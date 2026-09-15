<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Psr\Http\Message\RequestInterface;

use function array_key_exists;
use function is_array;
use function is_scalar;
use function parse_str;
use function sprintf;
use function var_export;

/**
 * Matches when the request query string contains at least the expected
 * parameters. Extra parameters on the request are ignored.
 */
final class QueryMatcher implements RequestMatcher
{
    /** @var array<array-key, mixed> */
    private readonly array $expected;

    /** @param array<array-key, mixed>|string $expected either ['code' => 'it'] or 'code=it&page=2' */
    public function __construct(array|string $expected)
    {
        if (is_array($expected)) {
            $this->expected = $expected;

            return;
        }

        parse_str($expected, $parsed);
        $this->expected = $parsed;
    }

    public function mismatch(RequestInterface $request): string|null
    {
        parse_str($request->getUri()->getQuery(), $actual);

        foreach ($this->expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                return sprintf('query is missing "%s"', $key);
            }

            if ($actual[$key] !== $value) {
                return sprintf(
                    'query "%s" is %s, not %s',
                    $key,
                    is_scalar($actual[$key]) ? var_export($actual[$key], true) : 'a different structure',
                    is_scalar($value) ? var_export($value, true) : 'the expected structure',
                );
            }
        }

        return null;
    }
}
