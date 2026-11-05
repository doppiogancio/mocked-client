<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Exception\AssertionFailed;
use DoppioGancio\MockedClient\MockResponse;
use DoppioGancio\MockedClient\MockServer;
use DoppioGancio\MockedClient\Tests\Fixture\CountryApi;
use DoppioGancio\MockedClient\Tests\Fixture\ResilientCountryApi;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

use function json_decode;
use function parse_str;

/** The examples printed in README.md and docs/ must keep working. */
class DocumentationTest extends TestCase
{
    /** The CountryApiTest printed in the README, run for real. */
    public function testReadmeCompleteExample(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/{code}')->reply(MockResponse::file(__DIR__ . '/fixtures/country-it.json'));

        $country = (new CountryApi($mock->psr18()))->find('IT');

        $this->assertSame('Italy', $country['name']);
        $mock->assertRequested('GET', '/country/IT');
    }

    public function testReadmeCompleteExampleRetry(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/{code}')->reply(MockResponse::sequence(
            MockResponse::failure('connection reset'),
            MockResponse::failure('connection reset'),
            MockResponse::json(['code' => 'IT', 'name' => 'Italy']),
        ));

        $country = (new ResilientCountryApi($mock->psr18()))->find('IT');

        $this->assertSame('Italy', $country['name']);
        $mock->assertRequestedTimes(3, 'GET', '/country/IT');
    }

    public function testReadmeOpeningExample(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/IT')->reply(MockResponse::json(['code' => 'IT', 'name' => 'Italy']));

        $client  = $mock->guzzle();
        $country = $client->request('GET', '/country/IT');

        $this->assertSame(
            ['code' => 'IT', 'name' => 'Italy'],
            json_decode((string) $country->getBody(), true),
        );
    }

    public function testReadmeFirstMatchWins(): void
    {
        $mock = MockServer::create();
        $mock->get('/country')->whereQuery(['code' => 'it'])->reply(MockResponse::json(['code' => 'IT']));
        $mock->get('/country')->whereQuery(['code' => 'de'])->reply(MockResponse::json(['code' => 'DE']));
        $mock->get('/country')->reply(MockResponse::file(__DIR__ . '/fixtures/countries.json'));

        $client = $mock->psr18();

        $this->assertSame('{"code":"IT"}', (string) $client->sendRequest(Request::get('/country?code=it'))->getBody());
        $this->assertSame('{"code":"DE"}', (string) $client->sendRequest(Request::get('/country?code=de'))->getBody());
        $this->assertStringContainsString(
            'Germany',
            (string) $client->sendRequest(Request::get('/country'))->getBody(),
        );
    }

    public function testReadmeCombinedMatchers(): void
    {
        $mock = MockServer::create();
        $mock->post('/orders')
            ->whereHeader('authorization', 'Bearer t')
            ->whereJson(['sku' => 'ABC'])
            ->reply(MockResponse::status(201));

        $request = Request::postJson('/orders', ['sku' => 'ABC'])->withHeader('authorization', 'Bearer t');

        $this->assertSame(201, $mock->psr18()->sendRequest($request)->getStatusCode());
    }

    public function testDocsPlaceholderConstraints(): void
    {
        $mock = MockServer::create();
        $mock->get('/orders/{id:\d+}')->reply(MockResponse::text('numeric'));
        $mock->get('/orders/new')->reply(MockResponse::text('the new order form'));

        $client = $mock->psr18();

        $this->assertSame('numeric', (string) $client->sendRequest(Request::get('/orders/42'))->getBody());
        $this->assertSame(
            'the new order form',
            (string) $client->sendRequest(Request::get('/orders/new'))->getBody(),
        );
    }

    public function testDocsCallbackMatcher(): void
    {
        $mock = MockServer::create();
        $mock->get('/country')
            ->whereCallback(
                static function (RequestInterface $request): bool {
                    parse_str($request->getUri()->getQuery(), $parameters);

                    return ($parameters['code'] ?? '') === 'AU';
                },
                'country code is AU',
            )
            ->reply(MockResponse::json(['code' => 'AU']));

        $this->assertSame(
            '{"code":"AU"}',
            (string) $mock->psr18()->sendRequest(Request::get('/country?code=AU'))->getBody(),
        );
    }

    public function testDocsRetryWithBackoffRecipe(): void
    {
        $mock = MockServer::create();
        $mock->get('/flaky')->reply(MockResponse::sequence(
            MockResponse::failure('connection reset'),
            MockResponse::failure('connection reset'),
            MockResponse::json(['ok' => true]),
        ));

        $client   = $mock->psr18();
        $attempts = 0;
        $body     = null;

        while ($attempts < 5) {
            $attempts++;
            try {
                $body = (string) $client->sendRequest(Request::get('/flaky'))->getBody();
                break;
            } catch (NetworkExceptionInterface) {
                continue;
            }
        }

        $this->assertSame('{"ok":true}', $body);
        $mock->assertRequestedTimes(3, 'GET', '/flaky');
    }

    public function testDocsPaginationRecipe(): void
    {
        $mock = MockServer::create();
        $mock->get('/items')->whereQuery(['page' => '1'])
            ->reply(MockResponse::json(['items' => [1, 2], 'next' => 2]));
        $mock->get('/items')->whereQuery(['page' => '2'])
            ->reply(MockResponse::json(['items' => [3], 'next' => null]));

        $client = $mock->psr18();
        $all    = [];
        $page   = 1;

        while ($page !== null) {
            $payload = json_decode((string) $client->sendRequest(Request::get('/items?page=' . $page))->getBody(), true);
            $all     = [...$all, ...$payload['items']];
            $page    = $payload['next'];
        }

        $this->assertSame([1, 2, 3], $all);
        $mock->assertAllStubsUsed();
    }

    public function testDocsAssertionFailureMessageShape(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/{code}')->reply(MockResponse::status(200));
        $client = $mock->psr18();
        $client->sendRequest(Request::get('/country/IT'));
        $client->sendRequest(Request::get('/country/DE'));

        try {
            $mock->assertRequested('POST', '/orders');
            $this->fail('expected AssertionFailed');
        } catch (AssertionFailed $e) {
            $this->assertStringContainsString(
                "Expected POST /orders to have been requested, but it was not.\n"
                . "Requests actually made:\n  GET /country/IT\n  GET /country/DE",
                $e->getMessage(),
            );
        }
    }

    public function testDocsErrorHandlingRecipe(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/XX')->reply(MockResponse::status(404));
        $mock->get('/country/YY')->reply(MockResponse::text('<html>oops</html>', 500));

        $client = $mock->psr18();

        $this->assertSame(404, $client->sendRequest(Request::get('/country/XX'))->getStatusCode());
        $this->assertSame(500, $client->sendRequest(Request::get('/country/YY'))->getStatusCode());
    }
}
