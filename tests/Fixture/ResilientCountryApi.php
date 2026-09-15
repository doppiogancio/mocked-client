<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Fixture;

use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;

use function json_decode;

/** The retrying service used by the README example. */
final class ResilientCountryApi
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $attempts = 3,
    ) {
    }

    /** @return array<string, mixed> */
    public function find(string $code): array
    {
        $request = new Request('GET', 'https://api.example.com/country/' . $code);

        for ($attempt = 1;; $attempt++) {
            try {
                return (array) json_decode((string) $this->client->sendRequest($request)->getBody(), true);
            } catch (NetworkExceptionInterface $e) {
                if ($attempt >= $this->attempts) {
                    throw $e;
                }
            }
        }
    }
}
