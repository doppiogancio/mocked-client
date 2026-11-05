# Upgrading from v4

v5 is a clean break. The four route builders, the `HandlerBuilder`/`ClientBuilder` bootstrapping and the `withXxx()` vocabulary are gone, replaced by `MockServer` and `MockResponse`. There are no deprecated aliases: the old names are removed.

## The shape of the change

```php
// v4
$handlerBuilder = new HandlerBuilder(
    Psr17FactoryDiscovery::findServerRequestFactory(),
    new NullLogger(),
);
$routeBuilder = new RouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);
$handlerBuilder->addRoute(
    $routeBuilder->new()
        ->withMethod('GET')
        ->withPath('/country/IT')
        ->withResponse(new Response(200, [], '{"code":"IT"}'))
        ->build(),
);
$client = (new ClientBuilder($handlerBuilder))->build();

// v5
$mock = MockServer::create();
$mock->get('/country/IT')->reply(MockResponse::json(['code' => 'IT']));
$client = $mock->guzzle();
```

## Method by method

| v4 | v5 |
| --- | --- |
| `new HandlerBuilder(...)` + `new ClientBuilder(...)` | `MockServer::create()` |
| `$routeBuilder->new()->withMethod('GET')->withPath('/a')` | `$mock->get('/a')` |
| `->withResponse($psr7)` | `->reply(MockResponse::psr7($psr7))` or `->reply($psr7)` |
| `->withStringResponse('body')` | `->reply(MockResponse::text('body'))` |
| `->withFileResponse('f.json')` | `->reply(MockResponse::file('f.json'))` |
| `->withHandler($closure)` | `->reply(MockResponse::using($closure))` |
| `ConditionalRouteBuilder` + `withConditionalStringResponse('code=it', ...)` | `->whereQuery('code=it')->reply(MockResponse::text(...))` |
| `CallbackRouteBuilder` + `withCallbackStringResponse($fn, ...)` | `->whereCallback($fn)->reply(MockResponse::text(...))` |
| `ConsecutiveCallsRouteBuilder` | `->reply(MockResponse::sequence(...))` |
| `$clientBuilder->build()` | `$mock->guzzle()` |
| (none) | `$mock->psr18()`, `$mock->httplug()`, `$mock->symfonyCallback()` |
| (none) | `$mock->assertRequested()` and the rest of the journal |

## Renamed exceptions

| v4 | v5 |
| --- | --- |
| `Exception\RouteNotFound` | `Exception\RequestNotMatched` |
| `Route\Exception\ResponseNotFound` | `Exception\RequestNotMatched` (one exception now covers both) |
| `Route\Exception\TooManyConsecutiveCalls` | `Exception\TooManyCalls` |
| `Route\Exception\FileNotFound` | `Exception\FileNotFound` |
| `Route\Exception\IncompleteRoute` | `Exception\IncompleteStub` |

All of them now implement `Exception\MockedClientException`, so `catch (MockedClientException $e)` catches anything this package throws. The ones raised while answering a request also implement `Psr\Http\Client\ClientExceptionInterface`.

## Behaviour that changed

- **Calling the response method twice no longer builds a queue.** In v4 a second `withStringResponse()` silently turned the route into a sequence. In v5 a second `reply()` replaces the response, and sequences are requested explicitly with `MockResponse::sequence()`.
- **Precedence is registration order.** v4 checked callbacks first, then query conditions, then the default, regardless of the order you wrote them. v5 tries stubs top to bottom and the first match wins, so a catch all must be registered last.
- **Responses are rebuilt per request.** In v4 the same response instance, and therefore the same already consumed body stream, was returned on every call, so a second `getContents()` came back empty. Each call now gets a fresh response.
- **A route with no matching condition throws.** v4's `ConditionalRouteBuilder` answered 404 when nothing matched. v5 throws `RequestNotMatched`, listing why each stub refused. Register a fallback stub if you want a 404.
- **An unreadable fixture throws where it is declared.** v4 used `assert()`, which does nothing when `zend.assertions` is off. v5 throws `FileNotFound` from `MockResponse::file()`.

## Dependency changes

- `league/route` is gone, along with `nikic/fast-route`, `psr/http-server-handler`, `psr/http-server-middleware` and `laravel/serializable-closure`. Path placeholders are now matched internally and still support `{code}` and `{id:\d+}`.
- `php-http/discovery` moved from a dev dependency to a real one: `MockServer::create()` needs it. In v4 the documented entry point would fatal on a fresh install.
- `guzzlehttp/guzzle` remains optional. So are `php-http/httplug` and `symfony/http-client`, each needed only for its adapter.
- Minimum PHP is unchanged at 8.2.
