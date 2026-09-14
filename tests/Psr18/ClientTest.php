<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Psr18;

use DoppioGancio\MockedClient\Psr18\Client;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\NullLogger;

class ClientTest extends TestCase
{
    public function testSendRequestWithoutGuzzle(): void
    {
        $client = new Client(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            new NullLogger(),
        );

        $routeBuilder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $client->addRoute(
            $routeBuilder
                ->withMethod('GET')
                ->withPath('/country/IT')
                ->withResponse(new Response(200, [], '{"code":"IT"}'))
                ->build(),
        );

        $response = $client->sendRequest(new Request('GET', '/country/IT'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"code":"IT"}', (string) $response->getBody());
    }

    public function testSendRequestThrowsClientExceptionInterface(): void
    {
        $client = new Client(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            new NullLogger(),
        );

        $this->expectException(ClientExceptionInterface::class);
        $client->sendRequest(new Request('GET', '/not/existing/route'));
    }
}
