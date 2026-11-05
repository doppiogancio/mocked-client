<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Fixture;

use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;

use function json_decode;

/** The service used by the README example. */
final class CountryApi
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    /** @return array<string, mixed> */
    public function find(string $code): array
    {
        $request = new Request('GET', 'https://api.example.com/country/' . $code);

        return (array) json_decode((string) $this->client->sendRequest($request)->getBody(), true);
    }
}
