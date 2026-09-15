<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\FileNotFound;
use DoppioGancio\MockedClient\Exception\NetworkFailure;
use DoppioGancio\MockedClient\Exception\TooManyCalls;
use DoppioGancio\MockedClient\MockResponse;
use DoppioGancio\MockedClient\MockServer;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

class MockResponseTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::create();
    }

    /**
     * The v4/v5-beta bug: the same response instance, and therefore the same
     * already consumed stream, was handed out on every call.
     */
    public function testBodyIsReadableOnEveryCallWithGetContents(): void
    {
        $this->server->get('/s')->reply(MockResponse::text('hello'));
        $client = $this->server->psr18();

        $first  = $client->sendRequest(Request::get('/s'))->getBody()->getContents();
        $second = $client->sendRequest(Request::get('/s'))->getBody()->getContents();

        $this->assertSame('hello', $first);
        $this->assertSame('hello', $second);
    }

    public function testEachCallGetsItsOwnResponseInstance(): void
    {
        $this->server->get('/s')->reply(MockResponse::text('hello'));
        $client = $this->server->psr18();

        $first  = $client->sendRequest(Request::get('/s'));
        $second = $client->sendRequest(Request::get('/s'));

        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->getBody(), $second->getBody());
    }

    public function testPsr7ResponseIsCopiedSoItStaysReusable(): void
    {
        $this->server->get('/s')->reply(MockResponse::psr7(new Response(201, ['x-a' => 'b'], 'body')));
        $client = $this->server->psr18();

        $first  = $client->sendRequest(Request::get('/s'));
        $second = $client->sendRequest(Request::get('/s'));

        $this->assertSame('body', $first->getBody()->getContents());
        $this->assertSame('body', $second->getBody()->getContents());
        $this->assertSame(201, $second->getStatusCode());
        $this->assertSame('b', $second->getHeaderLine('x-a'));
    }

    public function testText(): void
    {
        $this->server->get('/a')->reply(MockResponse::text('plain', 201, ['x-n' => 'v']));

        $response = $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('plain', (string) $response->getBody());
        $this->assertSame('v', $response->getHeaderLine('x-n'));
    }

    public function testJsonSetsContentType(): void
    {
        $this->server->get('/a')->reply(MockResponse::json(['code' => 'IT']));

        $response = $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->assertSame('{"code":"IT"}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testFile(): void
    {
        $this->server->get('/a')->reply(MockResponse::file(__DIR__ . '/fixtures/country.json'));

        $response = $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->assertJsonStringEqualsJsonString(
            '{"id":"+49","code":"DE","name":"Germany"}',
            (string) $response->getBody(),
        );
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testMissingFileFailsWhereItIsDeclared(): void
    {
        $this->expectException(FileNotFound::class);

        MockResponse::file(__DIR__ . '/fixtures/nope.json');
    }

    public function testStatus(): void
    {
        $this->server->delete('/a')->reply(MockResponse::status(204));

        $this->assertSame(204, $this->server->psr18()->sendRequest(Request::delete('/a'))->getStatusCode());
    }

    public function testUsingBuildsTheResponseFromTheRequest(): void
    {
        $this->server->get('/echo')->reply(MockResponse::using(
            static fn ($request) => MockResponse::text($request->getHeaderLine('x-request-id')),
        ));

        $request  = Request::get('/echo')->withHeader('x-request-id', 'abc123');
        $response = $this->server->psr18()->sendRequest($request);

        $this->assertSame('abc123', (string) $response->getBody());
    }

    public function testUsingAcceptsAPlainString(): void
    {
        $this->server->get('/a')->reply(MockResponse::using(static fn () => 'raw'));

        $this->assertSame('raw', (string) $this->server->psr18()->sendRequest(Request::get('/a'))->getBody());
    }

    public function testSequenceServesOneResponsePerCall(): void
    {
        $this->server->get('/job')->reply(MockResponse::sequence(
            MockResponse::json(['status' => 'pending']),
            MockResponse::json(['status' => 'pending']),
            MockResponse::json(['status' => 'done']),
        ));
        $client = $this->server->psr18();

        $bodies = [];
        for ($i = 0; $i < 3; $i++) {
            $bodies[] = (string) $client->sendRequest(Request::get('/job'))->getBody();
        }

        $this->assertSame(
            ['{"status":"pending"}', '{"status":"pending"}', '{"status":"done"}'],
            $bodies,
        );
    }

    public function testExhaustedSequenceThrows(): void
    {
        $this->server->get('/job')->reply(MockResponse::sequence(MockResponse::text('one')));
        $client = $this->server->psr18();
        $client->sendRequest(Request::get('/job'));

        $this->expectException(TooManyCalls::class);
        $this->expectExceptionMessage('was called 2 time(s), but its response sequence only defines 1');

        $client->sendRequest(Request::get('/job'));
    }

    public function testFailureSimulatesATransportError(): void
    {
        $this->server->get('/flaky')->reply(MockResponse::failure('connection timed out'));

        $this->expectException(NetworkFailure::class);
        $this->expectExceptionMessage('connection timed out');

        $this->server->psr18()->sendRequest(Request::get('/flaky'));
    }

    public function testFailureIsAPsr18NetworkException(): void
    {
        $this->server->get('/flaky')->reply(MockResponse::failure());

        try {
            $this->server->psr18()->sendRequest(Request::get('/flaky'));
            $this->fail('expected a network failure');
        } catch (ClientExceptionInterface $e) {
            $this->assertInstanceOf(NetworkFailure::class, $e);
        }
    }

    public function testReplyAcceptsAPsr7ResponseDirectly(): void
    {
        $this->server->get('/a')->reply(new Response(418, [], 'teapot'));

        $response = $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('teapot', (string) $response->getBody());
    }
}
