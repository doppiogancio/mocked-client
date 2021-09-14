<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Route;

use DoppioGancio\MockedClient\Route\ConditionalRouteBuilder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function assert;

class ConditionalRouteBuilderTest extends TestCase
{
    public function testRoute(): void
    {
        $builder = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder->withMethod('GET')
            ->withPath('/country')
            ->withConditionalResponse('page=2&code=it', new Response(201))
            ->withConditionalResponse('code=de', new Response(301))
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=it&page=2'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testDefaultRouteNotFound(): void
    {
        $builder = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder->withMethod('GET')
            ->withPath('/country')
            ->withConditionalResponse('page=2&code=it', new Response(201))
            ->withConditionalResponse('code=de', new Response(301))
            ->withConditionalResponse('page=4', new Response(401))
            ->withDefaultNotFoundResponse(new Response(123))
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=fr&page=3'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(123, $response->getStatusCode());
    }
}
