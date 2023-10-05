## Route with callbacks

```php
$builder = new CallbackRouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

$route = $builder->withMethod('GET')
    ->withPath('/country')
    ->withStringResponse(static function (RequestInterface $request): bool {
        parse_str($request->getUri()->getQuery(), $requestParameters);

        return ($requestParameters['code'] ?? '') === 'AU';
    }, '{"id":"+43","code":"AU","name":"Austria"}')
    ->withStringResponse(static function (RequestInterface $request): bool {
        parse_str($request->getUri()->getQuery(), $requestParameters);

        return ($requestParameters['code'] ?? '') === 'IT';
    }, '{"id":"+39","code":"IT","name":"Italy"}')
    ->build();

// Request #1
$response = $route->getHandler()(new Request('GET', '/country?code=AU'));
assert($response instanceof ResponseInterface);

$this->assertEquals(200, $response->getStatusCode());

$data = json_decode($response->getBody()->getContents(), true);
$this->assertEquals('Austria', $data['name']);

// Request #2
$response = $route->getHandler()(new Request('GET', '/country?code=IT'));
assert($response instanceof ResponseInterface);

$this->assertEquals(200, $response->getStatusCode());

$data = json_decode($response->getBody()->getContents(), true);
$this->assertEquals('Italy', $data['name']);

// Request #3 - Response not found
$this->expectException(ResponseNotFound::class);
$route->getHandler()(new Request('GET', '/country'));
```