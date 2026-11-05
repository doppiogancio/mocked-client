<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Matching;

use function is_string;
use function preg_match;
use function preg_quote;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Compiles a route path into a regex, supporting "/country/{code}" and
 * "/orders/{id:\d+}". This is the whole reason the package no longer needs a
 * routing library.
 */
final class PathPattern
{
    /** A placeholder name, plus an optional regex constraint that may itself contain {n,m} quantifiers. */
    private const PLACEHOLDER = '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^{}]*(?:\{[0-9,]+\}[^{}]*)*))?\}#';

    private readonly string $regex;

    public function __construct(private readonly string $path)
    {
        $this->regex = self::compile($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function matches(string $path): bool
    {
        return preg_match($this->regex, self::normalise($path)) === 1;
    }

    /** @return array<string, string> the placeholder values, empty when the path does not match */
    public function parameters(string $path): array
    {
        if (preg_match($this->regex, self::normalise($path), $matches) !== 1) {
            return [];
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }

    /** Leading slash guaranteed, trailing slash ignored, so "/a/" and "/a" are the same route. */
    public static function normalise(string $path): string
    {
        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * Literal segments are quoted, placeholders are not: quoting the whole
     * path first would escape the constraints we need to keep as regex.
     */
    private static function compile(string $path): string
    {
        $path   = self::normalise($path);
        $regex  = '';
        $offset = 0;

        while (preg_match(self::PLACEHOLDER, $path, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start  = $matches[0][1];
            $regex .= preg_quote(substr($path, $offset, $start - $offset), '#');

            $constraint = isset($matches[2]) && $matches[2][0] !== '' ? $matches[2][0] : '[^/]+';
            $regex     .= sprintf('(?P<%s>%s)', $matches[1][0], $constraint);

            $offset = $start + strlen($matches[0][0]);
        }

        $regex .= preg_quote(substr($path, $offset), '#');

        return sprintf('#^%s$#', $regex);
    }
}
