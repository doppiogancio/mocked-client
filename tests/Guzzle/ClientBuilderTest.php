<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Guzzle;

use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use DoppioGancio\MockedClient\MockedClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function json_decode;

class ClientBuilderTest extends TestCase
{
    private MockedClient $mockedClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockedClient = MockedClient::create();
    }

    /** @throws GuzzleException */
    public function testClientWithBaseRoute(): void
    {
        $response = $this->getMockedClient()->request('GET', 'http://www.any.com/country/IT');
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
        $client = $this->getMockedClient();

        $this->mockedClient->patch('/lazy/builder')->respondWith('', 123);

        $response = $client->request('PATCH', '/lazy/builder');
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
        $this->mockedClient->get('/country/')
            ->respondWhen('code=de', '{"id":"+49","code":"DE","name":"Germany"}')
            ->respondWhen('code=it', '{"id":"+39","code":"IT","name":"Italy"}')
            ->respondWithFile(__DIR__ . '/fixtures/countries.json');

        $this->mockedClient->get('/country/IT')
            ->respondWith('{"id":"+39","code":"IT","name":"Italy"}');

        $this->mockedClient->get('country/AU')
            ->respondWith('{"id":"+43","code":"AU","name":"Austria"}');

        $this->mockedClient->get('/country/DE/json')
            ->respondWithFile(__DIR__ . '/fixtures/country.json');

        $this->mockedClient->get('/admin/dashboard')
            ->respondWith('', 401);

        $this->mockedClient->get('/slow/api')
            ->respondWith('Gateway timeout', 504);

        $this->mockedClient->get('/headers')
            ->respondUsing(static function (RequestInterface $request) {
                return new Response(200, [], $request->getHeaderLine('test-header'));
            });

        $this->mockedClient->post('/body')
            ->respondUsing(static function (RequestInterface $request) {
                return new Response(200, [], $request->getBody()->getContents());
            });

        $this->mockedClient->get('/middleware')
            ->respondUsing(static function (RequestInterface $request) {
                return new Response(200, [], $request->getHeader('x-name')[0]);
            });

        // Anonymous middleware
        $middleware = new class ('x-name', 'x-value') extends Middleware {
            public function __construct(private readonly string $header, private readonly string $value)
            {
            }

            protected function mapRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader($this->header, $this->value);
            }
        };

        return $this->mockedClient->guzzleClient([$middleware]);
    }
}
