<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Symfony;

use Closure;
use DoppioGancio\MockedClient\MockServer;
use Http\Discovery\Psr17FactoryDiscovery;
use Symfony\Component\HttpClient\Response\MockResponse as SymfonyMockResponse;

use function explode;
use function is_array;
use function is_string;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Bridges the MockServer to Symfony's own MockHttpClient, which already knows
 * how to build Symfony responses. Reimplementing HttpClientInterface here would
 * mean reimplementing Symfony's response and stream classes too.
 */
final class SymfonyResponseFactory
{
    public function __construct(private readonly MockServer $server)
    {
    }

    public function build(): Closure
    {
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory  = Psr17FactoryDiscovery::findStreamFactory();

        /** @param array<string, mixed> $options */
        return function (
            string $method,
            string $url,
            array $options = [],
        ) use (
            $requestFactory,
            $streamFactory,
        ): SymfonyMockResponse {
            $request = $requestFactory->createRequest($method, $url);

            foreach (self::headersFrom($options) as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            if (isset($options['body']) && is_string($options['body']) && $options['body'] !== '') {
                $request = $request->withBody($streamFactory->createStream($options['body']));
            }

            $response = $this->server->handle($request);

            $headers = [];
            foreach ($response->getHeaders() as $name => $values) {
                $headers[$name] = $values;
            }

            return new SymfonyMockResponse((string) $response->getBody(), [
                'http_code' => $response->getStatusCode(),
                'response_headers' => $headers,
            ]);
        };
    }

    /**
     * Symfony normalises headers to a list of "Name: value" strings, but a
     * plain map is accepted too.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private static function headersFrom(array $options): array
    {
        if (! isset($options['headers']) || ! is_array($options['headers'])) {
            return [];
        }

        $headers = [];
        foreach ($options['headers'] as $key => $value) {
            if (is_string($key) && ! is_string($value)) {
                continue;
            }

            if (is_string($key) && ! str_contains((string) $value, ':')) {
                $headers[$key] = (string) $value;

                continue;
            }

            if (! is_string($value) || ! str_contains($value, ':')) {
                continue;
            }

            [$name, $headerValue]             = explode(':', $value, 2);
            $headers[strtolower(trim($name))] = trim($headerValue);
        }

        return $headers;
    }
}
