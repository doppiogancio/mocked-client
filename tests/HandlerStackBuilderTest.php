<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClientTests;

use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\MockedGuzzleClientBuilder;
use DoppioGancio\MockedClient\Route\ConditionalRouteBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function json_decode;

class HandlerStackBuilderTest extends TestCase
{
    public function testClientWithBaseRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    public function testClientWithFileRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/DE/json');
        $body     = (string) $response->getBody();
        $country  = json_decode($body, true);

        $this->assertEquals('+49', $country['id']);
        $this->assertEquals('DE', $country['code']);
        $this->assertEquals('Germany', $country['name']);
    }

    public function testClientWithResponse(): void
    {
        $response = $this->getMockedClient()->request('GET', '/admin/dashboard');
        $this->assertEquals(401, $response->getStatusCode(), 'http status not matching');
    }

    public function testRouteNotFound(): void
    {
        $this->expectExceptionMessage('Mocked route GET /not/existing/route not found');
        $this->getMockedClient()->request('GET', '/not/existing/route');
    }

    public function testWithConditionalRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/?size=2');
        $this->assertEquals(200, $response->getStatusCode(), 'http status not matching');
        $body = (string) $response->getBody();
        $list = json_decode($body, true);
        $this->assertCount(2, $list);
    }

    public function testWithConditionalRouteDefaultResponse(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/?sort_by=code');
        $this->assertEquals(200, $response->getStatusCode(), 'http status not matching');
        $body = (string) $response->getBody();
        $list = json_decode($body, true);
        $this->assertCount(0, $list);
    }

    private function getMockedClient(): Client
    {
        $handlerBuilder = new HandlerBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findServerRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $cb = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $countriesRoute = $cb->new()
            ->withMethod('GET')
            ->withPath('/country/')
            ->withConditionalFileResponse('size=2', __DIR__ . '/fixtures/list_of_2_countries.json')
            ->withConditionalFileResponse('size=3', __DIR__ . '/fixtures/list_of_3_countries.json')
            ->withDefaultFileResponse(__DIR__ . '/fixtures/list_of_countries.json')
            ->build();

        $handlerBuilder->addRoute(
            $countriesRoute->getMethod(),
            $countriesRoute->getPath(),
            $countriesRoute->getHandler(),
        );

        $handlerBuilder->addRoute(
            'GET',
            '/country/IT',
            static function (ServerRequestInterface $request): Response {
                return new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}');
            }
        );

        $handlerBuilder->addRouteWithFile('GET', '/country/DE/json', __DIR__ . '/fixtures/country.json');
        $handlerBuilder->addRouteWithResponse('GET', '/admin/dashboard', new Response(401));

        $clientBuilder = new MockedGuzzleClientBuilder($handlerBuilder);

        return $clientBuilder->build();
    }
}
