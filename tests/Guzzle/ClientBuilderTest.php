<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Guzzle;

use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\Guzzle\ClientBuilder;
use DoppioGancio\MockedClient\Route\ConditionalRouteBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

use function json_decode;

class ClientBuilderTest extends TestCase
{
    private HandlerBuilder $handlerBuilder;
    private RouteBuilder $routeBuilder;

    private ConditionalRouteBuilder $conditionalRouteBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handlerBuilder = new HandlerBuilder(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            new NullLogger(),
        );

        $this->routeBuilder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $this->conditionalRouteBuilder = new ConditionalRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    /** @throws GuzzleException */
    public function testClientWithBaseRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    /** @throws GuzzleException */
    public function testClientWithQueryStrings(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/?page=1&code=it');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    /** @throws GuzzleException */
    public function testClientWithDefaultResponse(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/');
        $body     = (string) $response->getBody();
        $this->assertCount(2, json_decode($body, true));
    }

    /** @throws GuzzleException */
    public function testClientWithFileRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/DE/json');
        $body     = (string) $response->getBody();
        $country  = json_decode($body, true);

        $this->assertEquals('+49', $country['id']);
        $this->assertEquals('DE', $country['code']);
        $this->assertEquals('Germany', $country['name']);
    }

    /** @throws GuzzleException */
    public function testRelativePath(): void
    {
        $response = $this->getMockedClient()->request('GET', 'country/AU');
        $body     = (string) $response->getBody();
        $country  = json_decode($body, true);

        $this->assertEquals('+43', $country['id']);
        $this->assertEquals('AU', $country['code']);
        $this->assertEquals('Austria', $country['name']);
    }

    /** @throws GuzzleException */
    public function testClientException(): void
    {
        $this->expectException(ClientException::class);
        $this->getMockedClient()->request('GET', '/admin/dashboard');
    }

    /** @throws GuzzleException */
    public function testServerException(): void
    {
        $this->expectException(ServerException::class);
        $this->getMockedClient()->request('GET', '/slow/api');
    }

    /** @throws GuzzleException */
    public function testRouteNotFound(): void
    {
        $this->expectExceptionMessage('Mocked route GET /not/existing/route not found');
        $this->getMockedClient()->request('GET', '/not/existing/route');
    }

    public function testRelativeRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', 'country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);
    }

    public function testLazyBuiltHandler(): void
    {
        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('PATCH')
                ->withPath('/lazy/builder')
                ->withResponse(new Response(123))
                ->build(),
        );

        $response = $this->getMockedClient()->request('PATCH', '/lazy/builder');
        $this->assertEquals(123, $response->getStatusCode());
    }

    public function testRequestWithHeaders(): void
    {
        $response = $this->getMockedClient()->request('GET', '/headers', [
            'headers' => ['test-header' => 'test-value'],
        ]);
        $body     = (string) $response->getBody();
        $this->assertEquals('test-value', $body);
    }

    public function testRequestWithJsonBody(): void
    {
        $response = $this->getMockedClient()->request('POST', '/body', [
            'json' => ['key' => 'value'],
        ]);
        $body     = (string) $response->getBody();
        $this->assertEquals('{"key":"value"}', $body);
    }

    public function testRequestWithFormBody(): void
    {
        $response = $this->getMockedClient()->request('POST', '/body', [
            'form_params' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ]);
        $body     = (string) $response->getBody();
        $this->assertEquals('key1=value1&key2=value2', $body);
    }

    public function testMiddlewares(): void
    {
        $response = $this->getMockedClient()->request('GET', '/middleware');
        $body     = (string) $response->getBody();
        $this->assertEquals('x-value', $body);
    }

    private function getMockedClient(): Client
    {
        $this->handlerBuilder->addRoute(
            $this->conditionalRouteBuilder->new()
                ->withMethod('GET')
                ->withPath('/country/')
                ->withConditionalResponse('code=de', new Response(200, [], '{"id":"+49","code":"DE","name":"Germany"}'))
                ->withConditionalResponse('code=it', new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
                ->withDefaultStringResponse('{}')
                ->withDefaultFileResponse(__DIR__ . '/fixtures/countries.json')
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('/country/IT')
                ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('country/AU')
                ->withResponse(new Response(200, [], '{"id":"+43","code":"AU","name":"Austria"}'))
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('/country/DE/json')
                ->withFileResponse(__DIR__ . '/fixtures/country.json')
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('/admin/dashboard')
                ->withResponse(new Response(401))
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('/slow/api')
                ->withStringResponse('Gateway timeout', 504)
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder
                ->withMethod('GET')
                ->withPath('/headers')
                ->withHandler(static function (Request $request): Response {
                    return new Response(200, [], $request->getHeaderLine('test-header'));
                })
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder
                ->withMethod('POST')
                ->withPath('/body')
                ->withHandler(static function (Request $request): Response {
                    return new Response(200, [], $request->getBody()->getContents());
                })
                ->build(),
        );

        $this->handlerBuilder->addRoute(
            $this->routeBuilder->new()
                ->withMethod('GET')
                ->withPath('/middleware')
                ->withHandler(static function (Request $request): Response {
                    return new Response(200, [], $request->getHeader('x-name')[0]);
                })
                ->build(),
        );

        $clientBuilder = new ClientBuilder($this->handlerBuilder);

        // Anonymous middleware
        $clientBuilder->addMiddleware(new class ('x-name', 'x-value') extends Middleware {
            public function __construct(private readonly string $header, private readonly string $value)
            {
            }

            protected function mapRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader($this->header, $this->value);
            }
        });

        return $clientBuilder->build();
    }
}
