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

## Usage - Mocking the client
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

## Usage - Using the mocked client
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
## Testing
```shell
$ composer run tests
```
