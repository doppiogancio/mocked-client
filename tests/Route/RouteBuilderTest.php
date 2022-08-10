<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Route;

use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function assert;

class RouteBuilderTest extends TestCase
{
    public function testRoute(): void
    {
        $builder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder
            ->withMethod('GET')
            ->withPath('/country')
            ->withResponse(new Response(123))
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=it&page=2'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(123, $response->getStatusCode());
    }

    public function testIncompleteRoute(): void
    {
        $this->expectExceptionMessage('Set parameter \"method\" before to build');

        $builder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $builder->withPath('/country')->build();
    }
}
