[![Packagist Version](https://img.shields.io/packagist/v/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)
[![Packagist Downloads](https://img.shields.io/packagist/dm/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)

# Mocked Client
This package will help test components that depend on clients for HTTP calls. It ships a Guzzle integration out of the box, and a dependency-free PSR-18 client for anything else that speaks PSR-18.

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
use DoppioGancio\MockedClient\Guzzle\HandlerBuilder;
use DoppioGancio\MockedClient\Guzzle\ClientBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Log\NullLogger;

$handlerBuilder = new HandlerBuilder(
    Psr17FactoryDiscovery::findServerRequestFactory(),
    new NullLogger()
);

$route = new RouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Route with Response
$handlerBuilder->addRoute(
    $route->new()
        ->withMethod('GET')
        ->withPath('/country/IT')
        ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
        ->build()
);


$clientBuilder = new ClientBuilder($handlerBuilder);
$client = $clientBuilder->build();
```

### Advanced examples
1. [Route with a file](./docs/route-with-file-response.md)
2. [Route with a string](./docs/route-with-string-response.md)
3. [Route with consecutive calls](./docs/route-with-consecutive-calls.md)
4. [Route with callbacks](./docs/route-with-callbacks.md)
5. [Guzzle client with middlewares](./docs/route-with-consecutive-calls.md)

## Mocking a plain PSR-18 client (no Guzzle required)
If the code under test only depends on `Psr\Http\Client\ClientInterface`, you don't need Guzzle at all:

```php
use DoppioGancio\MockedClient\Psr18\Client;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Log\NullLogger;

$client = new Client(
    Psr17FactoryDiscovery::findServerRequestFactory(),
    new NullLogger()
);

$route = new RouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

$client->addRoute(
    $route->new()
        ->withMethod('GET')
        ->withPath('/country/IT')
        ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
        ->build()
);

$response = $client->sendRequest(Psr17FactoryDiscovery::findRequestFactory()->createRequest('GET', '/country/IT'));
```

## How to use the client
```php
$response = $client->request('GET', '/country/DE/json');
$body = (string) $response->getBody();
$country = json_decode($body, true);

print_r($country);

// will return
Array
(
    [id] => +49
    [code] => DE
    [name] => Germany
)
```

## Some recommendations...
### Fail Fast, Fail Often
If you don't know in advance which routes are needed, don't worry, start with a client without routes, and let it suggests which routes to add.
```php
$handlerBuilder = new HandlerBuilder(
    Psr17FactoryDiscovery::findServerRequestFactory(),
    new NullLogger()
);

// don't add any route for now...

$clientBuilder = new ClientBuilder($handlerBuilder);
$client = $clientBuilder->build();
```

Run the test: the test will fail, but it will suggest you the route that is missing. 
By doing this, it will only specify the needed routes.

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

## Upgrading from v4 to v5
v5 is a breaking release focused on decoupling the core from Guzzle and modernising tooling. See [CHANGELOG.md](./CHANGELOG.md) for the full list of changes. In short:
- Minimum PHP version is now 8.2.
- `DoppioGancio\MockedClient\HandlerBuilder` moved to `DoppioGancio\MockedClient\Guzzle\HandlerBuilder`. Update your `use` statement, nothing else changes.
- `DoppioGancio\MockedClient\Route\RouteBuilderFacade` (unused, empty class) was removed.
- A missing file passed to `withFileResponse()` now throws `DoppioGancio\MockedClient\Route\Exception\FileNotFound` instead of relying on a runtime `assert()` (which could be silently disabled via `zend.assertions`).
- New: `DoppioGancio\MockedClient\Psr18\Client`, a Guzzle-free PSR-18 client, for tests that don't use Guzzle.

