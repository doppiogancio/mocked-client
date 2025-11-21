# Guzzle client with middlewares

You can attach the same middleware stack you use in real Guzzle clients to the mocked client. This is useful for cross-cutting concerns such as logging, tracing, retries or adding headers.

## Registering a middleware

```php
use DoppioGancio\MockedClient\Guzzle\ClientBuilder;
use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use DoppioGancio\MockedClient\HandlerBuilder;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

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
        ->withPath('/middleware')
        ->withResponse(new Response(200, [], 'ok'))
        ->build(),
);

$clientBuilder = new ClientBuilder($handlerBuilder);

$clientBuilder->addMiddleware(new class ('x-name', 'x-value') extends Middleware {
    public function __construct(private readonly string $header, private readonly string $value)
    {
    }

    protected function mapRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->header, $this->value);
    }
});

$client = $clientBuilder->build();
$response = $client->request('GET', '/middleware');
$header   = $response->getHeaderLine('x-name'); // "x-value"
```

## Typical use cases

- **Logging**: forward requests and responses to a logger to understand how the mocked client is used.
- **Tracing**: inject correlation IDs so that mocked calls still propagate context across your services.
- **Retries**: mimic retry behaviour to match the production stack.
- **Instrumentation**: measure latency or collect metrics from mocked interactions.
