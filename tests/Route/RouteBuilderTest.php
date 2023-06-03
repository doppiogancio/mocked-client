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
use function parse_str;

class RouteBuilderTest extends TestCase
{
    public function testRoute(): void
    {
        $builder = $this->getRouteBuilder();

        $route = $builder
            ->withMethod('GET')
            ->withPath('/country')
            ->withResponse(new Response(123))
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=it&page=2'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(123, $response->getStatusCode());
    }

    public function testRequestWithHeaders(): void
    {
        $builder = $this->getRouteBuilder();

        $route = $builder
            ->withMethod('GET')
            ->withPath('/header')
            ->withHandler(static function (Request $request): Response {
                return new Response(200, [], $request->getHeaderLine('test-header'));
            })
            ->build();

        $request  = (new Request('GET', '/header'))->withHeader('test-header', 'test-value');
        $response = $route->getHandler()($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals('test-value', $response->getBody()->getContents());
    }

    public function testRequestWithBody(): void
    {
        $builder = $this->getRouteBuilder();

        $route = $builder
            ->withMethod('POST')
            ->withPath('/body')
            ->withHandler(static function (Request $request): Response {
                return new Response(200, [], $request->getBody()->getContents());
            })
            ->build();

        $request  = (new Request('GET', '/body'))->withBody(
            Psr17FactoryDiscovery::findStreamFactory()->createStream('{"key": "value"}'),
        );
        $response = $route->getHandler()($request);
        assert($response instanceof ResponseInterface);
        $this->assertEquals('{"key": "value"}', $response->getBody()->getContents());
    }

    public function testRouteWithHandler(): void
    {
        $builder = $this->getRouteBuilder();

        $route = $builder
            ->withMethod('GET')
            ->withPath('/country')
            ->withHandler(static function (Request $request): ResponseInterface {
                parse_str($request->getUri()->getQuery(), $queryString);
                if ($queryString['page'] === '2') {
                    return new Response(123);
                }

                return new Response(222);
            })
            ->build();

        $response = $route->getHandler()(new Request('GET', '/country?nonce=12345&code=it&page=2'));
        assert($response instanceof ResponseInterface);
        $this->assertEquals(123, $response->getStatusCode());
    }

    public function testIncompleteRoute(): void
    {
        $this->expectExceptionMessage('Set parameter \"method\" before to build');

        $builder = $this->getRouteBuilder();

        $builder->withPath('/country')->build();
    }

    private function getRouteBuilder(): RouteBuilder
    {
        return new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }
}
