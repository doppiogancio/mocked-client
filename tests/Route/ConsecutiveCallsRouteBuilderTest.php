<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Route;

use DoppioGancio\MockedClient\Route\ConsecutiveCallsRouteBuilder;
use DoppioGancio\MockedClient\Route\Exception\TooManyConsecutiveCalls;
use GuzzleHttp\Psr7\Request;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function json_decode;

class ConsecutiveCallsRouteBuilderTest extends TestCase
{
    public function testRoute(): void
    {
        $builder = new ConsecutiveCallsRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder->withMethod('GET')
            ->withPath('/country')
            ->withStringResponse('{"id":"+43","code":"AU","name":"Austria"}')
            ->withStringResponse('{"id":"+39","code":"IT","name":"Italy"}')
            ->build();

        // Request #1
        $response = $route->getHandler()(new Request('GET', '/country'));
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Austria', $data['name']);

        // Request #2
        $response = $route->getHandler()(new Request('GET', '/country'));
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Italy', $data['name']);

        // Request #3 - Called too many times
        $this->expectException(TooManyConsecutiveCalls::class);
        $route->getHandler()(new Request('GET', '/country'));
    }
}
