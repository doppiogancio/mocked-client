## Route with response from file

```php
use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\ClientBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Log\NullLogger;

require_once "vendor/autoload.php";

$handlerBuilder = new HandlerBuilder(
    Psr17FactoryDiscovery::findServerRequestFactory(),
    new NullLogger()
);

$route = new RouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

// Route with File
$handlerBuilder->addRoute(
    $route->new()
        ->withMethod('GET')
        ->withPath('/country/DE')
        ->withFileResponse(
            file: __DIR__ . '/fixtures/country.json',
            httpStatus: 201,
            headers: ['content-type' => 'application/json']
        )
        ->build()
);

$clientBuilder = new ClientBuilder($handlerBuilder);
$client = $clientBuilder->build();
```