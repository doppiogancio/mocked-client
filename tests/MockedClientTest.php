<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\MockedClient;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;

class MockedClientTest extends TestCase
{
    public function testSameRoutesServeBothAGuzzleAndAPsr18Client(): void
    {
        $mockedClient = MockedClient::create();
        $mockedClient->get('/country/IT')->respondWith('{"code":"IT"}');

        $guzzleResponse = $mockedClient->guzzleClient()->request('GET', '/country/IT');
        $this->assertEquals('{"code":"IT"}', (string) $guzzleResponse->getBody());

        $psr18Response = $mockedClient->psr18Client()->sendRequest(
            Psr17FactoryDiscovery::findRequestFactory()->createRequest('GET', '/country/IT'),
        );
        $this->assertEquals('{"code":"IT"}', (string) $psr18Response->getBody());
    }

    public function testOnAllowsAnyHttpMethod(): void
    {
        $mockedClient = MockedClient::create();
        $mockedClient->on('OPTIONS', '/country/IT')->respondWith('', 204);

        $response = $mockedClient->guzzleClient()->request('OPTIONS', '/country/IT');
        $this->assertEquals(204, $response->getStatusCode());
    }
}
