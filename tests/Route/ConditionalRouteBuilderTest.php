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
use function json_decode;

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
            ->withDefaultFileResponse(__DIR__ . '/../fixtures/countries.json')
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=fr&page=3'));
        assert($response instanceof ResponseInterface);

        $body      = (string) $response->getBody();
        $countries = json_decode($body, true);
        $this->assertCount(2, $countries);
    }

    public function testQueryStringWithArrayNotation(): void
    {
        $builder = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder->withMethod('GET')
            ->withPath('/manufacturers')
            ->withConditionalFileResponse(
                'filters%5BhasContent%5D=1',
                __DIR__ . '/../fixtures/api_parts_manufacturers.json'
            )
            ->build();

        $response = $route->getHandler()(new Request('GET', '/manufacturers?filters[hasContent]=1'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(200, $response->getStatusCode());

        assert($response instanceof ResponseInterface);
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertCount(2, $data['manufacturers']);
    }
}
