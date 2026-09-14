# Changelog

## v5.0.0 (unreleased)

### Breaking changes
- Minimum PHP version raised from 8.1 to 8.2.
- `DoppioGancio\MockedClient\HandlerBuilder` moved to `DoppioGancio\MockedClient\Guzzle\HandlerBuilder`. It now delegates to the new, Guzzle-free `RequestHandler`.
- Removed the unused, empty `DoppioGancio\MockedClient\Route\RouteBuilderFacade`.
- `Builder::buildResponseFromFile()` (used by `withFileResponse()`) now throws `DoppioGancio\MockedClient\Route\Exception\FileNotFound` when the file cannot be opened, instead of relying on `assert()`, which is silently skipped when `zend.assertions` is disabled (the default in production).
- `league/route` bumped from `^5.1` to `^6.2` (internal implementation detail, not part of the public API; `^7.0` was considered but requires PHP 8.3+, incompatible with this package's PHP 8.2 minimum).

### Added
- `DoppioGancio\MockedClient\RequestHandler`: a framework-agnostic core that turns a PSR-7 request into the PSR-7 response of the matching mocked route, with no dependency on Guzzle.
- `DoppioGancio\MockedClient\Psr18\Client`: a dependency-free `Psr\Http\Client\ClientInterface` implementation for mocking any PSR-18 consumer, not just Guzzle.
- `DoppioGancio\MockedClient\Exception\RouteNotFound` now implements `Psr\Http\Client\ClientExceptionInterface`.
- `psr/http-client` added as an explicit dependency.

### Fixed
- `ConditionalRouteBuilder`'s default 404 response is now built via the injected PSR-17 `ResponseFactoryInterface` instead of instantiating `GuzzleHttp\Psr7\Response` directly, removing a stray Guzzle coupling in otherwise client-agnostic code.
- `HandlerBuilder`/`RequestHandler` no longer builds the relative request URI with `GuzzleHttp\Psr7\Uri`; it's done with plain string handling against the PSR-7 `UriInterface` already on the request.

### CI / tooling
- GitHub Actions now runs on PHP 8.2, 8.3 and 8.4, and also on pull requests.
- PHPStan bumped to `^2.2`.

## v4.1.4 and earlier
See git history.
