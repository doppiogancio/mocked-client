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
use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\MockedGuzzleClientBuilder;
use DoppioGancio\MockedClient\Route\ConditionalRouteBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
// ... more imports

$handlerBuilder = new HandlerBuilder(
    Psr17FactoryDiscovery::findServerRequestFactory(),
);

$cb = new ConditionalRouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

$rb = new RouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Route with Response
$handlerBuilder->addRoute(
    $rb->new()
        ->withMethod('GET')
        ->withPath('/country/IT')
        ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
        ->build()
);

// Route with File
$handlerBuilder->addRoute(
    $rb->new()
        ->withMethod('GET')
        ->withPath('/country/DE/json')
        ->withFileResponse(__DIR__ . '/fixtures/country.json')
        ->build()
);

// Route with Conditional responses
$handlerBuilder->addRoute(
    $cb->new()
        ->withMethod('GET')
        ->withPath('/country/')
        ->withConditionalResponse('code=de', new Response(200, [], '{"id":"+49","code":"DE","name":"Germany"}'))
        ->withConditionalFileResponse('code=it', __DIR__ . '/../fixtures/country_it.json')
        ->withConditionalStringResponse('code=fr', '{"id":"+33","code":"FR","name":"France"}')
        ->withDefaultFileResponse(__DIR__ . '/fixtures/countries.json')
        ->build()
);

// Route with a Guzzle Timeout
$handlerBuilder->addRoute(
      $rb->new()
          ->withMethod('GET')
          ->withPath('/slow/api')
          ->withException(new ConnectException(
              'Timed out after 30 seconds',
              new Request('GET', '/slow/api')
          ))
          ->build()
  );

$clientBuilder = new MockedGuzzleClientBuilder($handlerBuilder);

return $clientBuilder->build();
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
