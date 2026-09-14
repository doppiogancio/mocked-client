[![Packagist Version](https://img.shields.io/packagist/v/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)
[![Packagist Downloads](https://img.shields.io/packagist/dm/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)

# Mocked Client
This package helps test components that depend on an HTTP client. Define your mocked routes once, then get either a mocked Guzzle client or a plain PSR-18 client out of them.

## Install
Via Composer

```shell
$ composer require doppiogancio/mocked-client php-http/discovery
```

Add Guzzle only if you use the Guzzle integration:

```shell
$ composer require guzzlehttp/guzzle
```

## Requirements
This version requires a minimum PHP version 8.2

## How to mock a client

```php
use DoppioGancio\MockedClient\MockedClient;

$mockedClient = MockedClient::create();

$mockedClient->get('/country/IT')
    ->respondWithJson(['id' => '+39', 'code' => 'IT', 'name' => 'Italy']);

$client = $mockedClient->guzzleClient();
```

That's it: `$client` is a real `GuzzleHttp\Client` that answers `GET /country/IT` with the JSON above, and throws `DoppioGancio\MockedClient\Exception\RouteNotFound` for anything else. `MockedClient::create()` discovers the PSR-17 factories for you via `php-http/discovery`; use `new MockedClient($responseFactory, $streamFactory)` if you want to provide your own.

## How to use the client
```php
$response = $client->request('GET', '/country/IT');
$body = (string) $response->getBody();
$country = json_decode($body, true);

print_r($country);

// will return
Array
(
    [id] => +39
    [code] => IT
    [name] => Italy
)
```

## Defining routes: one object, every mocking style
`get()`/`post()`/`put()`/`patch()`/`delete()` (and `on($method, $path)` for anything else) return a `RouteExpectation`. Combine as many of these as you need on the same route:

```php
$mockedClient->get('/country')
    // a query string that must match wins first
    ->respondWhen('code=it', '{"id":"+39","code":"IT","name":"Italy"}')
    ->respondWhen('code=de', '{"id":"+49","code":"DE","name":"Germany"}')
    // otherwise, this is the fallback
    ->respondWithFile(__DIR__ . '/fixtures/countries.json');
```

1. [Respond with a string or a file](./docs/route-with-string-response.md)
2. [Respond only when the query string matches](./docs/route-with-conditional-response.md)
3. [Respond only when a callback matches the request](./docs/route-with-callbacks.md)
4. [Respond with a different value on each consecutive call](./docs/route-with-consecutive-calls.md)
5. [Guzzle client with middlewares](./docs/guzzle-client-with-middlewares.md)

Need full control over the response? `respondUsing(Closure $handler)` gets the incoming PSR-7 request and builds the response yourself:

```php
$mockedClient->get('/echo-header')
    ->respondUsing(fn ($request) => $responseFactory
        ->createResponse(200)
        ->withBody($streamFactory->createStream($request->getHeaderLine('x-request-id'))));
```

## Mocking a plain PSR-18 client (no Guzzle required)
If the code under test only depends on `Psr\Http\Client\ClientInterface`, skip Guzzle entirely — same `MockedClient`, same routes:

```php
$mockedClient = MockedClient::create();
$mockedClient->get('/country/IT')->respondWithJson(['code' => 'IT']);

$client = $mockedClient->psr18Client();

$response = $client->sendRequest($requestFactory->createRequest('GET', '/country/IT'));
```

You can even get both a Guzzle client and a PSR-18 client backed by the exact same routes from a single `MockedClient` instance.

## Some recommendations...
### Fail Fast, Fail Often
If you don't know in advance which routes are needed, don't worry: start with a client with no routes and let it tell you which one is missing.
```php
$mockedClient = MockedClient::create();
// don't add any route for now...
$client = $mockedClient->guzzleClient();
```

Run the test: it will fail, but the exception tells you exactly which route to add — so you only ever define the routes you actually need.

An example:
```shell
DoppioGancio\MockedClient\Exception\RouteNotFound: Mocked route GET /admin/dashboard not found
```

### Inject the client in the service container
If you have a service container, add the client to it, so that every service depending on it will be able to auto wire.
```php
self::$container->set(Client::class, $client);

// In Symfony
self::$container->set('eight_points_guzzle.client.my_client', $client);
```

## Advanced: embedding the core in your own wiring
`MockedClient` is a thin facade over a few independent pieces you can use directly if you need to:
- `DoppioGancio\MockedClient\RequestHandler`: framework-agnostic core, turns a PSR-7 request into a PSR-7 response, no Guzzle dependency.
- `DoppioGancio\MockedClient\Guzzle\HandlerBuilder` / `Guzzle\ClientBuilder`: adapt a `RequestHandler` to a Guzzle `HandlerStack`/`Client`.
- `DoppioGancio\MockedClient\Psr18\Client`: adapt a `RequestHandler` to `Psr\Http\Client\ClientInterface` directly.
- `DoppioGancio\MockedClient\RouteExpectation`: the fluent response builder returned by `MockedClient::get()` and friends; construct it yourself if you're building routes outside of `MockedClient`.

## Upgrading from v4 to v5
v5 is a breaking release that replaces the four separate route builders (`RouteBuilder`, `ConditionalRouteBuilder`, `ConsecutiveCallsRouteBuilder`, `CallbackRouteBuilder`) and the `HandlerBuilder`/`ClientBuilder` bootstrapping with the `MockedClient` facade and `RouteExpectation` described above. See [CHANGELOG.md](./CHANGELOG.md) for the full list of changes and a side-by-side migration example.
