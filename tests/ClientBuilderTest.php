<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\MockedGuzzleClientBuilder;
use DoppioGancio\MockedClient\Route\ConditionalRouteBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use DoppioGancio\MockedClient\Tests\Middleware\AddRequestHeader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function json_decode;

class ClientBuilderTest extends TestCase
{
    public function testMiddlewares(): void
    {
        $response = $this->getMockedClient()->request('GET', '/middleware');
        $body     = (string) $response->getBody();
        $this->assertEquals('x-value', $body);
    }

    public function testClientWithBaseRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    public function testClientWithQueryStrings(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/?page=1&code=it');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    public function testClientWithDefaultResponse(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/');
        $body     = (string) $response->getBody();
        $this->assertCount(2, json_decode($body, true));
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

    public function testClientException(): void
    {
        $this->expectException(ClientException::class);
        $this->getMockedClient()->request('GET', '/admin/dashboard');
    }

    public function testServerException(): void
    {
        $this->expectException(ServerException::class);
        $this->getMockedClient()->request('GET', '/slow/api');
    }

    public function testRouteNotFound(): void
    {
        $this->expectExceptionMessage('Mocked route GET /not/existing/route not found');
        $this->getMockedClient()->request('GET', '/not/existing/route');
    }

    public function testClientWithFullUrl(): void
    {
        $response = $this->getMockedClient()->request('GET', 'http://user:password@localhost:8099/country/?page=1&code=it');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    public function testRelativeRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', 'country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    private function getMockedClient(): Client
    {
        $handlerBuilder = new HandlerBuilder(
            Psr17FactoryDiscovery::findServerRequestFactory(),
        );

        $cb = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $rb = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $handlerBuilder->addRoute(
            $cb->new()
                ->withMethod('GET')
                ->withPath('/country/')
                ->withConditionalResponse('code=de', new Response(200, [], '{"id":"+49","code":"DE","name":"Germany"}'))
                ->withConditionalResponse('code=it', new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
                ->withDefaultFileResponse(__DIR__ . '/fixtures/countries.json')
                ->build()
        );

        $handlerBuilder->addRoute(
            $rb->new()
                ->withMethod('GET')
                ->withPath('/country/IT')
                ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
                ->build()
        );

        $handlerBuilder->addRoute(
            $rb->new()
                ->withMethod('GET')
                ->withPath('/country/DE/json')
                ->withFileResponse(__DIR__ . '/fixtures/country.json')
                ->build()
        );

        $handlerBuilder->addRoute(
            $rb->new()
                ->withMethod('GET')
                ->withPath('/admin/dashboard')
                ->withResponse(new Response(401))
                ->build()
        );

        $handlerBuilder->addRoute(
            $rb->new()
                ->withMethod('GET')
                ->withPath('/slow/api')
                ->withStringResponse('Gateway timeout', 504)
                ->build()
        );

        $handlerBuilder->addRoute(
            $rb->new()
                ->withMethod('GET')
                ->withPath('/middleware')
                ->withHandler(static function (RequestInterface $request): ResponseInterface {
                    return new Response(200, [], $request->getHeader('x-name')[0]);
                })
                ->build()
        );

        $clientBuilder = new MockedGuzzleClientBuilder($handlerBuilder);
        $clientBuilder->addMiddleware(new AddRequestHeader('x-name', 'x-value'));

        return $clientBuilder->build();
    }
}
