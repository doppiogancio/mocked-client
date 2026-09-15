<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\AssertionFailed;
use DoppioGancio\MockedClient\Exception\RequestNotMatched;
use DoppioGancio\MockedClient\MockResponse;
use DoppioGancio\MockedClient\MockServer;
use PHPUnit\Framework\TestCase;

class JournalTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::create();
    }

    public function testRecordsWhatWasActuallySent(): void
    {
        $this->server->post('/orders')->reply(MockResponse::status(201));

        $request = Request::postJson('/orders', ['sku' => 'ABC', 'qty' => 2])
            ->withHeader('authorization', 'Bearer t');
        $this->server->psr18()->sendRequest($request);

        $calls = $this->server->recorded('POST', '/orders');

        $this->assertCount(1, $calls);
        $this->assertSame(['sku' => 'ABC', 'qty' => 2], $calls[0]->jsonBody());
        $this->assertSame('Bearer t', $calls[0]->header('authorization'));
        $this->assertSame(201, $calls[0]->response?->getStatusCode());
        $this->assertSame('POST /orders', $calls[0]->matchedStub);
    }

    public function testRecordedCanFilterByPattern(): void
    {
        $this->server->get('/country/{code}')->reply(MockResponse::status(200));
        $client = $this->server->psr18();
        $client->sendRequest(Request::get('/country/IT'));
        $client->sendRequest(Request::get('/country/DE'));

        $this->assertCount(2, $this->server->recorded('GET', '/country/{code}'));
        $this->assertCount(1, $this->server->recorded('GET', '/country/IT'));
    }

    public function testLastRequest(): void
    {
        $this->server->get('/a')->reply(MockResponse::status(200));
        $this->server->get('/b')->reply(MockResponse::status(200));
        $client = $this->server->psr18();
        $client->sendRequest(Request::get('/a'));
        $client->sendRequest(Request::get('/b'));

        $this->assertSame('/b', $this->server->lastRequest()?->getUri()->getPath());
    }

    public function testUnmatchedRequestsAreRecordedToo(): void
    {
        try {
            $this->server->psr18()->sendRequest(Request::get('/missing'));
        } catch (RequestNotMatched) {
            // expected
        }

        $unmatched = $this->server->journal()->unmatched();

        $this->assertCount(1, $unmatched);
        $this->assertSame('/missing', $unmatched[0]->path());
    }

    public function testAssertRequested(): void
    {
        $this->server->get('/country/{code}')->reply(MockResponse::status(200));
        $this->server->psr18()->sendRequest(Request::get('/country/IT'));

        $this->server->assertRequested('GET', '/country/{code}');
        $this->server->assertRequested('GET', '/country/IT');
        $this->expectNotToPerformAssertions();
    }

    public function testAssertRequestedListsWhatWasActuallyCalled(): void
    {
        $this->server->get('/a')->reply(MockResponse::status(200));
        $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->expectException(AssertionFailed::class);
        $this->expectExceptionMessage('Expected POST /orders to have been requested, but it was not.');

        $this->server->assertRequested('POST', '/orders');
    }

    public function testAssertRequestedReportsAnEmptyJournal(): void
    {
        $this->expectException(AssertionFailed::class);
        $this->expectExceptionMessage('No requests were made.');

        $this->server->assertRequested('GET', '/a');
    }

    public function testAssertNotRequested(): void
    {
        $this->server->get('/a')->reply(MockResponse::status(200));
        $this->server->psr18()->sendRequest(Request::get('/a'));

        $this->server->assertNotRequested('DELETE', '/a');

        $this->expectException(AssertionFailed::class);
        $this->expectExceptionMessage('Expected GET /a never to be requested, but it was requested 1 time(s).');

        $this->server->assertNotRequested('GET', '/a');
    }

    public function testAssertRequestedTimes(): void
    {
        $this->server->get('/poll')->reply(MockResponse::status(200));
        $client = $this->server->psr18();
        $client->sendRequest(Request::get('/poll'));
        $client->sendRequest(Request::get('/poll'));

        $this->server->assertRequestedTimes(2, 'GET', '/poll');

        $this->expectException(AssertionFailed::class);
        $this->expectExceptionMessage('Expected GET /poll to be requested 3 time(s), got 2.');

        $this->server->assertRequestedTimes(3, 'GET', '/poll');
    }

    public function testAssertAllStubsUsed(): void
    {
        $this->server->get('/used')->reply(MockResponse::status(200));
        $this->server->get('/never')->reply(MockResponse::status(200));
        $this->server->psr18()->sendRequest(Request::get('/used'));

        $this->expectException(AssertionFailed::class);
        $this->expectExceptionMessage("1 stub(s) were never requested:\n  GET /never");

        $this->server->assertAllStubsUsed();
    }

    public function testAssertAllStubsUsedPassesWhenEverythingWasHit(): void
    {
        $this->server->get('/used')->reply(MockResponse::status(200));
        $this->server->psr18()->sendRequest(Request::get('/used'));

        $this->server->assertAllStubsUsed();
        $this->expectNotToPerformAssertions();
    }
}
