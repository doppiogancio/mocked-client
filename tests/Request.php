<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use Nyholm\Psr7\Request as Psr7Request;
use Psr\Http\Message\RequestInterface;

use function json_encode;

/** Small helper so the tests read as HTTP rather than as PSR-7 plumbing. */
final class Request
{
    public static function get(string $path): RequestInterface
    {
        return self::make('GET', $path);
    }

    public static function delete(string $path): RequestInterface
    {
        return self::make('DELETE', $path);
    }

    public static function post(string $path, string $body = ''): RequestInterface
    {
        return self::make('POST', $path, $body);
    }

    /** @param array<array-key, mixed> $data */
    public static function postJson(string $path, array $data): RequestInterface
    {
        return self::make('POST', $path, (string) json_encode($data))
            ->withHeader('content-type', 'application/json');
    }

    public static function make(string $method, string $path, string $body = ''): RequestInterface
    {
        // A host is included on purpose: routing must ignore it.
        return new Psr7Request($method, 'https://api.example.com' . $path, [], $body === '' ? null : $body);
    }
}
