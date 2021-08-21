<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClientTests;

use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\MockedGuzzleClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\Test\TestLogger;

use function json_decode;

class HandlerStackBuilderTest extends TestCase
{
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new TestLogger();
    }

    public function testClientWithBaseRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', '/country/IT');
        $body     = (string) $response->getBody();
        $this->assertEquals('{"id":"+39","code":"IT","name":"Italy"}', $body);

        $this->assertTrue($this->logger->hasDebugThatContains('GET /country/IT'));
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
        $this->assertTrue(
            $this->logger->hasErrorThatContains('League\Route\Http\Exception\NotFoundException Not Found')
        );
    }

    private function getMockedClient(): Client
    {
        $handlerBuilder = new HandlerBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findServerRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $this->logger
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

        $clientBuilder = new MockedGuzzleClientBuilder($handlerBuilder, $this->logger);

        return $clientBuilder->build();
    }
}
