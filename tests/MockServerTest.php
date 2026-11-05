<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\IncompleteStub;
use DoppioGancio\MockedClient\Exception\RequestNotMatched;
use DoppioGancio\MockedClient\MockResponse;
use DoppioGancio\MockedClient\MockServer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

use function parse_str;

class MockServerTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::create();
    }

    public function testRoutesOnMethodAndPath(): void
    {
        $this->server->get('/country')->reply(MockResponse::text('get'));
        $this->server->post('/country')->reply(MockResponse::text('post'));

        $client = $this->server->psr18();

        $this->assertSame('get', (string) $client->sendRequest(Request::get('/country'))->getBody());
        $this->assertSame('post', (string) $client->sendRequest(Request::post('/country'))->getBody());
    }

    public function testRoutesOnPathPlaceholders(): void
    {
        $this->server->get('/country/{code}')->reply(MockResponse::text('one country'));

        $this->assertSame(
            'one country',
            (string) $this->server->psr18()->sendRequest(Request::get('/country/IT'))->getBody(),
        );
    }

    public function testTheHostIsIgnored(): void
    {
        $this->server->get('/country')->reply(MockResponse::text('ok'));

        $request = Request::make('GET', '/country');

        $this->assertSame('ok', (string) $this->server->psr18()->sendRequest($request)->getBody());
    }

    public function testFirstMatchingStubWins(): void
    {
        $this->server->get('/country')->whereQuery(['code' => 'it'])->reply(MockResponse::text('Italy'));
        $this->server->get('/country')->whereQuery(['code' => 'de'])->reply(MockResponse::text('Germany'));
        $this->server->get('/country')->reply(MockResponse::text('all countries'));

        $client = $this->server->psr18();

        $this->assertSame('Italy', (string) $client->sendRequest(Request::get('/country?code=it'))->getBody());
        $this->assertSame('Germany', (string) $client->sendRequest(Request::get('/country?code=de'))->getBody());
        $this->assertSame('all countries', (string) $client->sendRequest(Request::get('/country?code=xx'))->getBody());
    }

    public function testQueryMatcherAcceptsAQueryString(): void
    {
        $this->server->get('/country')->whereQuery('code=it&page=2')->reply(MockResponse::text('Italy p2'));
        $this->server->get('/country')->reply(MockResponse::text('fallback'));

        $client = $this->server->psr18();

        $this->assertSame('Italy p2', (string) $client->sendRequest(Request::get('/country?page=2&code=it'))->getBody());
        $this->assertSame('fallback', (string) $client->sendRequest(Request::get('/country?page=3&code=it'))->getBody());
    }

    public function testHeaderMatcher(): void
    {
        $this->server->get('/me')->whereHeader('authorization', 'Bearer good')->reply(MockResponse::status(200));
        $this->server->get('/me')->reply(MockResponse::status(401));

        $client = $this->server->psr18();

        $authorised = Request::get('/me')->withHeader('authorization', 'Bearer good');
        $this->assertSame(200, $client->sendRequest($authorised)->getStatusCode());
        $this->assertSame(401, $client->sendRequest(Request::get('/me'))->getStatusCode());
    }

    public function testHeaderMatcherCanRequirePresenceOnly(): void
    {
        $this->server->get('/me')->whereHeader('x-trace')->reply(MockResponse::text('traced'));
        $this->server->get('/me')->reply(MockResponse::text('untraced'));

        $client = $this->server->psr18();

        $this->assertSame(
            'traced',
            (string) $client->sendRequest(Request::get('/me')->withHeader('x-trace', 'anything'))->getBody(),
        );
        $this->assertSame('untraced', (string) $client->sendRequest(Request::get('/me'))->getBody());
    }

    public function testJsonBodyMatcherIgnoresExtraKeys(): void
    {
        $this->server->post('/orders')->whereJson(['sku' => 'ABC'])->reply(MockResponse::status(201));
        $this->server->post('/orders')->reply(MockResponse::status(422));

        $client = $this->server->psr18();

        $good = Request::postJson('/orders', ['sku' => 'ABC', 'qty' => 2, 'note' => 'gift']);
        $bad  = Request::postJson('/orders', ['sku' => 'XYZ', 'qty' => 2]);

        $this->assertSame(201, $client->sendRequest($good)->getStatusCode());
        $this->assertSame(422, $client->sendRequest($bad)->getStatusCode());
    }

    public function testJsonBodyMatcherGoesDeep(): void
    {
        $this->server->post('/orders')
            ->whereJson(['customer' => ['id' => 7]])
            ->reply(MockResponse::status(201));
        $this->server->post('/orders')->reply(MockResponse::status(422));

        $client = $this->server->psr18();

        $good = Request::postJson('/orders', ['customer' => ['id' => 7, 'name' => 'Ada']]);
        $bad  = Request::postJson('/orders', ['customer' => ['id' => 8, 'name' => 'Ada']]);

        $this->assertSame(201, $client->sendRequest($good)->getStatusCode());
        $this->assertSame(422, $client->sendRequest($bad)->getStatusCode());
    }

    public function testCallbackMatcher(): void
    {
        $hasCode = static function (string $code): callable {
            return static function (RequestInterface $request) use ($code): bool {
                parse_str($request->getUri()->getQuery(), $parameters);

                return ($parameters['code'] ?? '') === $code;
            };
        };

        $this->server->get('/country')->whereCallback($hasCode('AU'))->reply(MockResponse::text('Austria'));
        $this->server->get('/country')->whereCallback($hasCode('IT'))->reply(MockResponse::text('Italy'));

        $client = $this->server->psr18();

        $this->assertSame('Austria', (string) $client->sendRequest(Request::get('/country?code=AU'))->getBody());
        $this->assertSame('Italy', (string) $client->sendRequest(Request::get('/country?code=IT'))->getBody());
    }

    public function testMatchersCombine(): void
    {
        $this->server->post('/orders')
            ->whereHeader('authorization', 'Bearer t')
            ->whereJson(['sku' => 'ABC'])
            ->reply(MockResponse::status(201));
        $this->server->post('/orders')->reply(MockResponse::status(403));

        $client = $this->server->psr18();

        $authorised = Request::postJson('/orders', ['sku' => 'ABC'])->withHeader('authorization', 'Bearer t');
        $anonymous  = Request::postJson('/orders', ['sku' => 'ABC']);

        $this->assertSame(201, $client->sendRequest($authorised)->getStatusCode());
        $this->assertSame(403, $client->sendRequest($anonymous)->getStatusCode());
    }

    public function testUnmatchedRequestExplainsEveryStub(): void
    {
        $this->server->get('/orders')->reply(MockResponse::status(200));
        $this->server->post('/orders')->whereQuery(['dry' => '1'])->reply(MockResponse::status(200));
        $this->server->post('/orders/{id}')->reply(MockResponse::status(200));

        try {
            $this->server->psr18()->sendRequest(Request::postJson('/orders', ['sku' => 'ABC']));
            $this->fail('expected RequestNotMatched');
        } catch (RequestNotMatched $e) {
            $message = $e->getMessage();

            $this->assertStringContainsString('No stub matched POST /orders', $message);
            $this->assertStringContainsString('3 stub(s) registered', $message);
            $this->assertStringContainsString('method is GET, not POST', $message);
            $this->assertStringContainsString('query is missing "dry"', $message);
            $this->assertStringContainsString('path does not match "/orders/{id}"', $message);
            $this->assertStringContainsString('{"sku":"ABC"}', $message);
        }
    }

    public function testUnmatchedRequestWithNoStubsSaysSo(): void
    {
        $this->expectException(RequestNotMatched::class);
        $this->expectExceptionMessage('No stubs are registered on this MockServer.');

        $this->server->psr18()->sendRequest(Request::get('/anything'));
    }

    public function testEveryDispatchExceptionIsAPsr18ClientException(): void
    {
        $this->server->get('/x')->whereQuery(['code' => 'it'])->reply(MockResponse::text('ita'));

        $this->expectException(ClientExceptionInterface::class);

        $this->server->psr18()->sendRequest(Request::get('/x?code=de'));
    }

    public function testStubWithoutAResponseSaysWhatIsMissing(): void
    {
        $this->server->get('/forgotten');

        $this->expectException(IncompleteStub::class);
        $this->expectExceptionMessage('The stub for GET /forgotten has no response');

        $this->server->psr18()->sendRequest(Request::get('/forgotten'));
    }

    public function testOneServerCanBackSeveralClients(): void
    {
        $this->server->get('/country')->reply(MockResponse::text('shared'));

        $psr18  = $this->server->psr18();
        $guzzle = $this->server->guzzle();

        $this->assertSame('shared', (string) $psr18->sendRequest(Request::get('/country'))->getBody());
        $this->assertSame('shared', (string) $guzzle->request('GET', '/country')->getBody());
        $this->assertSame(2, $this->server->journal()->count('GET', '/country'));
    }
}
