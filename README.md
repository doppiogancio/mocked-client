<div align="center">

# Mocked Client

**Test the code that talks to an HTTP API, without the HTTP API.**

Describe a fake server once, hand it to the code under test as whatever client it expects: PSR-18, Guzzle, HTTPlug or Symfony.

[![Packagist Version](https://img.shields.io/packagist/v/doppiogancio/mocked-client?style=flat-square&color=4c1)](https://packagist.org/packages/doppiogancio/mocked-client)
[![Packagist Downloads](https://img.shields.io/packagist/dm/doppiogancio/mocked-client?style=flat-square)](https://packagist.org/packages/doppiogancio/mocked-client)
[![PHP Version](https://img.shields.io/packagist/dependency-v/doppiogancio/mocked-client/php?style=flat-square)](https://packagist.org/packages/doppiogancio/mocked-client)
[![Build](https://img.shields.io/github/actions/workflow/status/doppiogancio/mocked-client/github-actions.yml?branch=main&style=flat-square)](https://github.com/doppiogancio/mocked-client/actions)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-4c1?style=flat-square)](https://phpstan.org/)
[![Licence](https://img.shields.io/packagist/l/doppiogancio/mocked-client?style=flat-square)](./LICENCE.md)

</div>

> [!IMPORTANT]
> **This README documents v5, which is currently in beta.**
> The stable release is **v4.1.4**, and that is what `composer require doppiogancio/mocked-client` installs today. Its documentation lives in the [v4.1.4 tag](https://github.com/doppiogancio/mocked-client/tree/v4.1.4).
> To try v5: `composer require --dev doppiogancio/mocked-client:^5.0@beta`. Coming from v4? Start with the [upgrade guide](./docs/90-upgrading-from-v4.md).

---

```php
$mock = MockServer::create();

$mock->get('/country/IT')->reply(MockResponse::json(['code' => 'IT', 'name' => 'Italy']));

$client = $mock->guzzle();   // a real GuzzleHttp\Client, answering from your stubs
```

Anything you did not stub fails immediately, with a message that names the request and tells you why every stub refused it. Your tests end up describing exactly the traffic they depend on, and nothing else.

## Contents

- [Why this library](#why-this-library)
- [Install](#install)
- [A complete example](#a-complete-example)
- [How it works](#how-it-works)
- [Matching a request](#matching-a-request)
- [Building a response](#building-a-response)
- [Checking what was requested](#checking-what-was-requested)
- [Choosing a client](#choosing-a-client)
- [Failing fast](#failing-fast)
- [Documentation](#documentation)

## Why this library

Most HTTP mocks are a **queue**: you push responses and they come back in order, so your test silently depends on how many calls your code makes and in which sequence. Refactor the code, break the test.

This one is a **router**. You describe endpoints, the way the real API is described, and the order of calls stops mattering.

|  | Guzzle `MockHandler` | `php-http/mock-client` | Symfony `MockHttpClient` | **Mocked Client** |
| --- | --- | --- | --- | --- |
| How a response is chosen | a queue, consumed in call order | a queue, consumed in call order | a queue, or a callback you write | **routed by the request** |
| Order of calls matters | yes | yes | with the queue form | **no** |
| Path placeholders | hand rolled | hand rolled | hand rolled | **built in** |
| Match on query, headers, JSON body | hand rolled | hand rolled | hand rolled | **built in** |
| Records requests for assertions | via the history middleware | yes | yes | **yes** |
| Simulates transport failures | yes | yes | yes | **yes** |
| Clients it can back | Guzzle, and PSR-18 through it | PSR-18, HTTPlug | Symfony | **PSR-18, Guzzle, HTTPlug, Symfony** |

If your project uses one HTTP client and always will, the native mock of that client is a perfectly good answer, and it is one less dependency. This package earns its place when you want to describe an API rather than a call sequence, or when different parts of your codebase reach for different clients.

## Install

While v5 is in beta, ask for it explicitly, otherwise Composer resolves to the v4.1.4 stable and none of this README applies:

```shell
composer require --dev doppiogancio/mocked-client:^5.0@beta
```

You also need a PSR-7 implementation, if your project does not already have one:

```shell
composer require --dev nyholm/psr7
```

Guzzle, HTTPlug and Symfony HttpClient are needed only for their respective adapters. Requires **PHP 8.2** or later.

## A complete example

A service that fetches a country, and the test that pins its behaviour:

```php
final class CountryApi
{
    public function __construct(private readonly ClientInterface $client) {}

    public function find(string $code): array
    {
        $request = new Request('GET', 'https://api.example.com/country/' . $code);

        return json_decode((string) $this->client->sendRequest($request)->getBody(), true);
    }
}
```

```php
final class CountryApiTest extends TestCase
{
    public function testItFetchesACountry(): void
    {
        $mock = MockServer::create();
        $mock->get('/country/{code}')->reply(MockResponse::file(__DIR__ . '/fixtures/country.json'));

        $country = (new CountryApi($mock->psr18()))->find('IT');

        $this->assertSame('Italy', $country['name']);
        $mock->assertRequested('GET', '/country/IT');
    }

    public function testItRetriesOnAFlakyConnection(): void
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
}
```

Note what the second test does: `MockResponse::failure()` throws a real `NetworkExceptionInterface`, the same thing a broken connection produces, so the retry loop is exercised rather than simulated.

## How it works

A stub is **one matcher chain** plus **one response**. The two are independent, so any matcher combines with any response:

```php
$mock->post('/orders')                          // method and path
     ->whereHeader('authorization', 'Bearer t') // narrow it
     ->whereJson(['sku' => 'ABC'])              // narrow it further
     ->reply(MockResponse::status(201));        // and answer
```

Stubs are tried **in registration order, first match wins**. There is no hidden priority: the order you read is the order that applies, so the general case goes last.

```php
$mock->get('/country')->whereQuery(['code' => 'it'])->reply(MockResponse::json($italy));
$mock->get('/country')->whereQuery(['code' => 'de'])->reply(MockResponse::json($germany));
$mock->get('/country')->reply(MockResponse::file(__DIR__ . '/fixtures/countries.json'));
```

## Matching a request

| Method | Matches when |
| --- | --- |
| `get()` `post()` `put()` `patch()` `delete()` `head()` `options()` `on()` | the method and path match. Paths accept placeholders: `/country/{code}`, `/orders/{id:\d+}` |
| `whereQuery(['code' => 'it'])` or `whereQuery('code=it&page=2')` | the query string contains at least these parameters |
| `whereHeader('authorization', 'Bearer t')` | the header has this value. Omit the value to only require its presence |
| `whereJson(['sku' => 'ABC'])` | the JSON body contains at least these keys, nested ones included |
| `whereCallback(fn (RequestInterface $r) => ...)` | your callback returns true |

Every `where*()` is a **subset** check, so a request may carry extra parameters, headers or JSON keys. A test pins the one thing it cares about and stays readable when the payload grows.

The host is ignored, so the same stubs work whether your code calls `/country` or `https://api.example.com/country`.

[Full reference](./docs/01-matching.md)

## Building a response

| Factory | Produces |
| --- | --- |
| `MockResponse::text('hi', 201, ['x-a' => 'b'])` | a plain body |
| `MockResponse::json(['code' => 'IT'])` | a JSON body, with `content-type` set for you |
| `MockResponse::file(__DIR__ . '/fixtures/c.json')` | a body read from a fixture |
| `MockResponse::status(204)` | a status code and nothing else |
| `MockResponse::psr7($response)` | a PSR-7 response you already have |
| `MockResponse::using(fn ($request) => ...)` | a response built from the request |
| `MockResponse::sequence($a, $b, $c)` | a different response on each consecutive call |
| `MockResponse::failure('timed out')` | a simulated transport error, for testing retries |

Every response is rebuilt for each request, body stream included, so an endpoint called twice hands out two fully readable responses.

[Full reference](./docs/02-responses.md)

## Checking what was requested

Stubbing says what the server answers. The journal says what your code actually asked:

```php
$mock->assertRequested('POST', '/orders');
$mock->assertRequestedTimes(3, 'GET', '/country/{code}');
$mock->assertNotRequested('DELETE', '/orders/1');
$mock->assertAllStubsUsed();
```

Assertions accept the same placeholders as stubs, so `'/country/{code}'` counts every country and `'/country/IT'` counts only that one. `assertAllStubsUsed()` catches the opposite problem: a stub written and never exercised, which usually means the test has drifted from the code.

When an assertion is not expressive enough, read the journal:

```php
$sent = $mock->recorded('POST', '/orders')[0];

$sent->jsonBody();                // ['sku' => 'ABC', 'qty' => 2]
$sent->header('content-type');    // 'application/json'
$sent->response?->getStatusCode();
```

[Full reference](./docs/04-assertions.md)

## Choosing a client

One server, every flavour. They all share the same stubs and the same journal, so you can hand a Guzzle client to one collaborator and a PSR-18 client to another in the same test.

```php
$mock->psr18();          // Psr\Http\Client\ClientInterface
$mock->guzzle();         // GuzzleHttp\Client, with optional middlewares and client options
$mock->guzzleHandler();  // a handler for a HandlerStack you build yourself
$mock->httplug();        // Http\Client\HttpAsyncClient
$mock->handle($request); // PSR-7 in, PSR-7 out, no client at all

// Symfony's own MockHttpClient does the response building
new Symfony\Component\HttpClient\MockHttpClient($mock->symfonyCallback());
```

Every exception raised while answering a request implements `Psr\Http\Client\ClientExceptionInterface`, as PSR-18 requires, so `catch (ClientExceptionInterface $e)` in the code under test behaves exactly as it would against a real client.

[Full reference](./docs/05-clients.md)

## Failing fast

You do not have to know which endpoints your code calls. Write no stubs, run the test, and read the failure:

```
No stub matched POST /orders

Request as received:
  POST /orders?dry=1
  content-type: application/json
  {"sku":"ABC","qty":2}

3 stub(s) registered:
  GET  /orders       method is GET, not POST
  POST /orders       query is missing "dry"
  POST /orders/{id}  path does not match "/orders/{id}"
```

It prints the request as received and, for each stub, the precise reason it refused. Add the stub it asks for and repeat, so you only ever write the stubs you actually need.

## Documentation

| | |
| --- | --- |
| [1. Matching requests](./docs/01-matching.md) | placeholders, query, headers, JSON body, callbacks |
| [2. Building responses](./docs/02-responses.md) | strings, JSON, fixtures, PSR-7, dynamic responses |
| [3. Sequences and failures](./docs/03-sequences.md) | consecutive calls, simulated transport errors |
| [4. Asserting requests](./docs/04-assertions.md) | the journal and the assertions |
| [5. Clients and adapters](./docs/05-clients.md) | PSR-18, Guzzle, HTTPlug, Symfony, containers |
| [6. Recipes](./docs/06-recipes.md) | retries, pagination, auth tokens, error handling |
| [7. Upgrading from v4](./docs/90-upgrading-from-v4.md) | the v5 migration table |

## Contributing

Issues and pull requests are welcome. Before opening one:

```shell
composer code-review   # fixes the coding standard, then runs PHPStan and the tests
```

The suite must stay green on every supported PHP version, PHPStan runs at level 8, and the coding standard is [Doctrine](https://github.com/doctrine/coding-standard). Examples printed in the documentation are executed in CI, so they cannot drift from the code.

## Licence

MIT. See [LICENCE.md](./LICENCE.md).

Built by [Fabrizio Gargiulo](https://github.com/doppiogancio).
