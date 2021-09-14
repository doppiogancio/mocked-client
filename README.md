# Mocked Client
A simple way to mock a client

## Install
Via Composer

```shell
$ composer require doppiogancio/mocked-client guzzlehttp/guzzle php-http/discovery
```

Note:
- The package `guzzlehttp/guzzle` is not required, 
  this package is compatible any http client that supports the PSR request/responses  
- The package `php-http/discovery` is not required, it is used to auto-discover the needed PSR factories
  (if you prefer you can provide the needed PSR factories manually)

## Requirements
This version requires a minimum PHP version 7.4

## How to mock a client
```php

use GuzzleHttp\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use MockedClient\HandlerBuilder;
use MockedClient\MockedGuzzleClientBuilder;
// ... more imports

$builder = new HandlerBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findServerRequestFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Add a route with a response via callback
$builder->addRoute(
    'GET', '/country/IT', static function (ServerRequestInterface $request): Response {
        return new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}');
    }
);

// Add a route with a response in a text file
$builder->addRouteWithFile('GET',  '/country/IT/json', __DIR__ . '/fixtures/country.json');

// Add a route with a response in a string
$builder->addRouteWithString('GET',  '/country/IT', '{"id":"+39","code":"IT","name":"Italy"}');

// Add a route mocking directly the response
$builder->addRouteWithResponse('GET', '/admin/dashboard', new Response(401));

$clientBuilder = new MockedGuzzleClientBuilder($builder);
$client = $clientBuilder->build();
```
## How to inject the mocked client in Symfony
```php
...
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
If you are testing a component that uses a client, don't worry, instantiate a mocked client without routes.
```php
$builder = new HandlerBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findServerRequestFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Add here the routes ...

$clientBuilder = new MockedGuzzleClientBuilder($builder);
$client = $clientBuilder->build();
```

inject it in the kernel container if needed
```php
self::$container->set('eight_points_guzzle.client.william', $client);
```

Run the test: the test will fail, but it will suggest you the route that is missing. 
By doing this, it will only specify the needed routes.

An example:
```shell
DoppioGancio\MockedClient\Exception\RouteNotFound : Mocked route GET /admin/dashboard not found
```

To mock for example an error response:
```php
$handlerBuilder->addRouteWithResponse('GET', '/admin/dashboard', new Response(401));
```
