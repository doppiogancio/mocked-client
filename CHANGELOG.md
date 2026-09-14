# Changelog

## v5.0.0 (unreleased)

### New primary API: `MockedClient` + `RouteExpectation`
The four separate route builders and the `HandlerBuilder`/`ClientBuilder` bootstrapping are replaced by a single facade and a single fluent response object:

```php
// v4
$handlerBuilder = new HandlerBuilder(Psr17FactoryDiscovery::findServerRequestFactory(), new NullLogger());
$routeBuilder   = new RouteBuilder(Psr17FactoryDiscovery::findResponseFactory(), Psr17FactoryDiscovery::findStreamFactory());
$handlerBuilder->addRoute(
    $routeBuilder->new()->withMethod('GET')->withPath('/country/IT')
        ->withResponse(new Response(200, [], '{"code":"IT"}'))
        ->build()
);
$client = (new ClientBuilder($handlerBuilder))->build();

// v5
$mockedClient = MockedClient::create();
$mockedClient->get('/country/IT')->respondWith('{"code":"IT"}');
$client = $mockedClient->guzzleClient();
```

`RouteExpectation` (returned by `get()`/`post()`/`put()`/`patch()`/`delete()`/`on()`) merges what used to be four mutually exclusive builders into one object that can combine all these behaviours on the same route:
- `respondWith()` / `respondWithJson()` / `respondWithFile()` / `respondWithResponse()`: call once for a static response, call more than once to queue consecutive responses (each call consumes the next one; `TooManyConsecutiveCalls` is thrown once exhausted).
- `respondWhen()` / `respondWhenFile()` / `respondWhenResponse()`: respond based on the request's query string (replaces `ConditionalRouteBuilder`). A plain `respondWith*()` call acts as the fallback.
- `respondIf()` / `respondIfFile()` / `respondIfResponse()`: respond based on a callback matching the request (replaces `CallbackRouteBuilder`).
- `respondUsing(Closure $handler)`: full escape hatch (replaces `RouteBuilder::withHandler()`).

`MockedClient::guzzleClient()` and `MockedClient::psr18Client()` build a mocked Guzzle client and a dependency-free PSR-18 client from the exact same set of routes.

### Breaking changes
- Minimum PHP version raised from 8.1 to 8.2.
- Removed `DoppioGancio\MockedClient\Route\RouteBuilder`, `ConditionalRouteBuilder`, `ConsecutiveCallsRouteBuilder`, `CallbackRouteBuilder`, `Builder`, `CallbackRouteHandler`, `ConsecutiveCallsRouteHandler`, and the unused, empty `RouteBuilderFacade`. Use `MockedClient` + `RouteExpectation` instead (see above).
- Removed `DoppioGancio\MockedClient\Route\Exception\IncompleteRoute` (no longer applicable: `MockedClient::on($method, $path)` always requires both upfront).
- `DoppioGancio\MockedClient\HandlerBuilder` moved to `DoppioGancio\MockedClient\Guzzle\HandlerBuilder`, and now wraps a `RequestHandler` instance instead of a `ServerRequestFactoryInterface`/`LoggerInterface` pair: `new Guzzle\HandlerBuilder($requestHandler)`.
- `DoppioGancio\MockedClient\Psr18\Client` likewise now takes a `RequestHandler`: `new Psr18\Client($requestHandler)`.
- A missing file passed to `respondWithFile()` (formerly `withFileResponse()`) now throws `DoppioGancio\MockedClient\Route\Exception\FileNotFound` instead of relying on `assert()`, which is silently skipped when `zend.assertions` is disabled (the default in production).
- A `RouteExpectation` built only with `respondWhen*()`/`respondIf*()` and no fallback now throws `ResponseNotFound` when nothing matches, instead of `ConditionalRouteBuilder`'s old silent `404` default.
- `league/route` bumped from `^5.1` to `^6.2` (internal implementation detail, not part of the public API; `^7.0` was considered but requires PHP 8.3+, incompatible with this package's PHP 8.2 minimum).

### Added
- `DoppioGancio\MockedClient\MockedClient`: the new facade described above.
- `DoppioGancio\MockedClient\RouteExpectation`: the new fluent per-route response builder described above.
- `DoppioGancio\MockedClient\RequestHandler`: a framework-agnostic core that turns a PSR-7 request into the PSR-7 response of the matching mocked route, with no dependency on Guzzle.
- `DoppioGancio\MockedClient\Psr18\Client`: a dependency-free `Psr\Http\Client\ClientInterface` implementation for mocking any PSR-18 consumer, not just Guzzle.
- `DoppioGancio\MockedClient\Exception\RouteNotFound` now implements `Psr\Http\Client\ClientExceptionInterface`.
- `psr/http-client` added as an explicit dependency.

### Fixed
- The default response for an unmatched conditional route is now built via the injected PSR-17 `ResponseFactoryInterface` instead of instantiating `GuzzleHttp\Psr7\Response` directly, removing a stray Guzzle coupling in otherwise client-agnostic code.
- The relative request URI is no longer built with `GuzzleHttp\Psr7\Uri`; it's done with plain string handling against the PSR-7 `UriInterface` already on the request.

### CI / tooling
- GitHub Actions now runs on PHP 8.2, 8.3 and 8.4, and also on pull requests.
- PHPStan bumped to `^2.2`.

## v4.1.4 and earlier
See git history.
