<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\RequestNotMatched;
use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use DoppioGancio\MockedClient\MockResponse;
use DoppioGancio\MockedClient\MockServer;
use Http\Client\HttpAsyncClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\MockHttpClient;

/** One MockServer, every flavour of client the ecosystem expects. */
class ClientsTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::create();
        $this->server->get('/country/{code}')->reply(MockResponse::json(['code' => 'IT']));
    }

    public function testPsr18(): void
    {
        $client = $this->server->psr18();

        $this->assertInstanceOf(ClientInterface::class, $client);
        $this->assertSame(
            '{"code":"IT"}',
            (string) $client->sendRequest(Request::get('/country/IT'))->getBody(),
        );
    }

    public function testGuzzle(): void
    {
        $response = $this->server->guzzle()->request('GET', '/country/IT');

        $this->assertSame('{"code":"IT"}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testGuzzleAcceptsClientOptions(): void
    {
        $client = $this->server->guzzle([], ['base_uri' => 'https://api.example.com']);

        $this->assertSame('{"code":"IT"}', (string) $client->request('GET', '/country/IT')->getBody());
    }

    public function testGuzzleMiddlewareSeesTheRequestFirst(): void
    {
        $server = MockServer::create();
        $server->get('/middleware')->reply(MockResponse::using(
            static fn (RequestInterface $request) => $request->getHeaderLine('x-added'),
        ));

        $middleware = new class extends Middleware {
            protected function mapRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('x-added', 'by-middleware');
            }
        };

        $response = $server->guzzle([$middleware])->request('GET', '/middleware');

        $this->assertSame('by-middleware', (string) $response->getBody());
    }

    public function testGuzzleSurfacesAnUnmatchedRequest(): void
    {
        $this->expectException(RequestNotMatched::class);

        $this->server->guzzle()->request('GET', '/nope');
    }

    public function testGuzzleHandlerCanBeUsedStandalone(): void
    {
        $handler  = $this->server->guzzleHandler();
        $response = $handler(Request::get('/country/IT'))->wait();

        $this->assertSame('{"code":"IT"}', (string) $response->getBody());
    }

    public function testHttplugAsync(): void
    {
        $client = $this->server->httplug();

        $this->assertInstanceOf(HttpAsyncClient::class, $client);

        $response = $client->sendAsyncRequest(Request::get('/country/IT'))->wait();

        $this->assertSame('{"code":"IT"}', (string) $response->getBody());
    }

    public function testHttplugRejectsOnAnUnmatchedRequest(): void
    {
        $promise = $this->server->httplug()->sendAsyncRequest(Request::get('/nope'));

        $this->expectException(RequestNotMatched::class);

        $promise->wait();
    }

    public function testSymfonyHttpClient(): void
    {
        $client = new MockHttpClient($this->server->symfonyCallback());

        $response = $client->request('GET', 'https://api.example.com/country/IT');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"code":"IT"}', $response->getContent());
    }

    public function testSymfonyHttpClientForwardsHeadersAndBody(): void
    {
        $server = MockServer::create();
        $server->post('/orders')
            ->whereHeader('authorization', 'Bearer t')
            ->whereJson(['sku' => 'ABC'])
            ->reply(MockResponse::status(201));

        $client = new MockHttpClient($server->symfonyCallback());

        $response = $client->request('POST', 'https://api.example.com/orders', [
            'headers' => ['authorization' => 'Bearer t'],
            'body' => '{"sku":"ABC"}',
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $server->assertRequested('POST', '/orders');
    }

    public function testGuzzleAndPsr18ShareTheJournal(): void
    {
        $this->server->guzzle()->request('GET', '/country/IT');
        $this->server->psr18()->sendRequest(Request::get('/country/DE'));

        $this->server->assertRequestedTimes(2, 'GET', '/country/{code}');
        $this->expectNotToPerformAssertions();
    }

    /**
     * The diagnostic message must survive the trip through the Guzzle handler
     * stack: it is the whole point of failing fast on an unknown route.
     */
    public function testGuzzleKeepsTheDiagnosticMessageIntact(): void
    {
        try {
            $this->server->guzzle()->request('GET', '/nope');
            $this->fail('expected RequestNotMatched');
        } catch (RequestNotMatched $e) {
            $this->assertStringContainsString('No stub matched GET /nope', $e->getMessage());
            $this->assertStringContainsString('path does not match "/country/{code}"', $e->getMessage());
        }
    }
}
