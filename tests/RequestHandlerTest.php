<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\RouteNotFound;
use DoppioGancio\MockedClient\RequestHandler;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RequestHandlerTest extends TestCase
{
    public function testHandleMatchedRoute(): void
    {
        $requestHandler = new RequestHandler(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            new NullLogger(),
        );

        $routeBuilder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $requestHandler->addRoute(
            $routeBuilder
                ->withMethod('GET')
                ->withPath('/country/IT')
                ->withResponse(new Response(200, [], '{"code":"IT"}'))
                ->build(),
        );

        $response = $requestHandler->handle(new Request('GET', 'http://www.any.com/country/IT'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"code":"IT"}', (string) $response->getBody());
    }

    public function testHandleUnmatchedRouteThrows(): void
    {
        $requestHandler = new RequestHandler(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            new NullLogger(),
        );

        $this->expectException(RouteNotFound::class);
        $requestHandler->handle(new Request('GET', '/not/existing/route'));
    }
}
