<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use Psr\Http\Message\RequestInterface;

use function array_key_exists;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

/**
 * Matches when the JSON request body contains at least the expected keys and
 * values, nested arrays included. Extra keys on the request are ignored, so a
 * test can pin the one field it cares about.
 */
final class JsonBodyMatcher implements RequestMatcher
{
    /** @param array<array-key, mixed> $expected */
    public function __construct(private readonly array $expected)
    {
    }

    public function mismatch(RequestInterface $request): string|null
    {
        $body = (string) $request->getBody();
        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }

        if ($body === '') {
            return 'request body is empty, expected JSON';
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return 'request body is not a JSON object or array';
        }

        $path = self::firstDifference($this->expected, $decoded);

        return $path === null ? null : sprintf('JSON body differs at %s', $path);
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     *
     * @return string|null dotted path of the first key that is missing or different
     */
    private static function firstDifference(array $expected, array $actual, string $prefix = ''): string|null
    {
        foreach ($expected as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (! array_key_exists($key, $actual)) {
                return sprintf('"%s" (missing)', $path);
            }

            if (is_array($value) && is_array($actual[$key])) {
                $nested = self::firstDifference($value, $actual[$key], $path);
                if ($nested !== null) {
                    return $nested;
                }

                continue;
            }

            if ($value !== $actual[$key]) {
                return sprintf('"%s" (got %s)', $path, (string) json_encode($actual[$key]));
            }
        }

        return null;
    }
}
