<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Psr18;

use DoppioGancio\MockedClient\Psr18\Client;
use DoppioGancio\MockedClient\RequestHandler;
use DoppioGancio\MockedClient\Route\Route;
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
        $client = $this->client();

        $client->addRoute(
            new Route('GET', '/country/IT', static fn () => new Response(200, [], '{"code":"IT"}')),
        );

        $response = $client->sendRequest(new Request('GET', '/country/IT'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"code":"IT"}', (string) $response->getBody());
    }

    public function testSendRequestThrowsClientExceptionInterface(): void
    {
        $this->expectException(ClientExceptionInterface::class);
        $this->client()->sendRequest(new Request('GET', '/not/existing/route'));
    }

    private function client(): Client
    {
        return new Client(
            new RequestHandler(
                Psr17FactoryDiscovery::findServerRequestFactory(),
                new NullLogger(),
            ),
        );
    }
}
