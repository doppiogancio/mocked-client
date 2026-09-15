# Changelog

## v5.0.0-beta.1 (2026-09-15)

A rewrite of the public API around two objects, plus the request journal the package never had. See [docs/90-upgrading-from-v4.md](./docs/90-upgrading-from-v4.md) for a method by method migration table.

> **This is a beta.** The API described below is what v5.0.0 is meant to ship, but it is not frozen yet: feedback that would change it is welcome while this tag is the latest. Composer will not install it unless you ask for it explicitly or set `minimum-stability` to `beta`.

### Added

- `MockServer`: the entry point. Describes a fake server with stubs and hands it out as a PSR-18, Guzzle, HTTPlug or Symfony client, all sharing the same stubs and journal.
- `MockResponse`: every kind of answer a stub can give, as orthogonal factories: `text()`, `json()`, `file()`, `status()`, `psr7()`, `using()`, `sequence()` and `failure()`.
- Matching split from responding. `whereQuery()`, `whereHeader()`, `whereJson()` and `whereCallback()` combine freely with any response, instead of the previous grid of twelve `respondWith*()`/`respondWhen*()`/`respondIf*()` methods that still had gaps in it.
- Matching on request headers and on the JSON request body, neither of which was possible declaratively before.
- `MockResponse::failure()` simulates a transport error as a `Psr\Http\Client\NetworkExceptionInterface`, for testing retries, backoff and circuit breakers.
- The request journal: `recorded()`, `lastRequest()`, `journal()`, and the assertions `assertRequested()`, `assertNotRequested()`, `assertRequestedTimes()` and `assertAllStubsUsed()`.
- HTTPlug adapter (`MockServer::httplug()`) and a Symfony HttpClient bridge (`MockServer::symfonyCallback()`).
- `Exception\MockedClientException`, a marker interface implemented by every exception the package throws.
- `RequestNotMatched` prints the request as received and, for every registered stub, the reason it did not match.

### Fixed

- **PSR-18 conformance.** `ResponseNotFound` and `TooManyConsecutiveCalls` were thrown from inside `sendRequest()` without implementing `ClientExceptionInterface`, so `catch (ClientExceptionInterface $e)` in the code under test did not catch them. Every exception raised while answering a request now implements it.
- **Responses are no longer shared between calls.** The same response instance, and therefore the same already consumed body stream, was handed out on every request: the second `getBody()->getContents()` returned an empty string. Each request now gets a freshly built response.
- **The router is no longer rebuilt on every request.** A new `League\Route\Router` was instantiated and every route remapped for each call.

### Changed

- `league/route` removed. Path placeholders (`/country/{code}`, `/orders/{id:\d+}`) are matched by an internal `PathPattern`. This also drops `nikic/fast-route`, `psr/http-server-handler`, `psr/http-server-middleware` and `laravel/serializable-closure`, and removes the need for a PSR-17 `ServerRequestFactoryInterface`.
- `php-http/discovery` moved from `require-dev` to `require`: `MockServer::create()` depends on it, so the documented entry point used to fatal on a fresh install.
- Stubs are tried in registration order, first match wins. The old fixed precedence (callbacks, then query conditions, then the default) is gone.
- Sequences are explicit. Calling the response method twice no longer silently turns a stub into a queue; use `MockResponse::sequence()`.
- A request that matches no stub throws `RequestNotMatched` instead of answering a silent 404.

### Removed

- `RouteBuilder`, `ConditionalRouteBuilder`, `ConsecutiveCallsRouteBuilder`, `CallbackRouteBuilder`, `Builder`, `RouteBuilderFacade`, `CallbackRouteHandler`, `ConsecutiveCallsRouteHandler`.
- `HandlerBuilder`, `Guzzle\ClientBuilder`, `Psr18\Client`, `RequestHandler`, `RouteExpectation`, `MockedClient`, `Route\Route`.
- `Route\Exception\IncompleteRoute`, replaced by `Exception\IncompleteStub`.

`Guzzle\Middleware\Middleware` is unchanged and still the base class for Guzzle middlewares.

### CI and tooling

- GitHub Actions runs on PHP 8.2, 8.3, 8.4 and 8.5, on pushes and pull requests.
- Minimum PHP stays at 8.2.

## v4.1.4 and earlier

See the git history.
