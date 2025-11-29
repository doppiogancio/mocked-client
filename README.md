[![Packagist Version](https://img.shields.io/packagist/v/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)
[![Packagist Downloads](https://img.shields.io/packagist/dm/doppiogancio/mocked-client)](https://packagist.org/packages/doppiogancio/mocked-client)

# Mocked Client
This package will help test components that depend on clients for HTTP calls. At the moment only Guzzle Client is supported.

## Install
Via Composer

```shell
$ composer require doppiogancio/mocked-client guzzlehttp/guzzle php-http/discovery
```

## Requirements
This version requires a minimum PHP version 8.1

## How to mock a client

```php
use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\ClientBuilder;
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
If you have a service container, add the client to it, so that every service depending on it will be able to auto wire. In Symfony
tests it is handy to wrap the setup in a private helper to keep fixtures close to your test logic and let services pull the client
from the container by name.

```php
use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\ClientBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Log\NullLogger;

private function injectGoogleApiClient(string $fixturePath): void
{
    $handlerBuilder = new HandlerBuilder(
        Psr17FactoryDiscovery::findServerRequestFactory(),
        new NullLogger(),
    );

    $route = new RouteBuilder(
        Psr17FactoryDiscovery::findResponseFactory(),
        Psr17FactoryDiscovery::findStreamFactory(),
    );

    $handlerBuilder->addRoute(
        $route->new()
            ->withMethod('GET')
            ->withPath('/external/resource')
            ->withResponse(new Response(200, [], file_get_contents($fixturePath)))
            ->build()
    );

    $clientBuilder = new ClientBuilder($handlerBuilder);
    $mockedClient = $clientBuilder->build();

    self::getContainer()->set('eight_points_guzzle.client.google_api', $mockedClient);
}
```

Benefits:

- Every service depending on `eight_points_guzzle.client.google_api` keeps working via autowiring.
- Complex responses can be loaded from JSON fixtures (even copied from real API calls), which also exercises your
  deserialization logic instead of hand-crafting PHP unit mocks.

For non-Symfony projects, you can apply the same approach by registering `$mockedClient` in your DI container using the class
name or service identifier your application expects.

