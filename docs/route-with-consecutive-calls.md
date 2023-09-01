## Route with consecutive calls

```php
$builder = new ConsecutiveCallsRouteBuilder(
    Psr17FactoryDiscovery::findResponseFactory(),
    Psr17FactoryDiscovery::findStreamFactory(),
);

$route = $builder->withMethod('GET')
    ->withPath('/country')
    ->withResponse(new Response(200, [], '{"id":"+39","code":"IT","name":"Italy"}'))
    ->withStringResponse(
        content: '{"id":"+33","code":"FR","name":"France"}',
        httpStatus: 201,
        headers: ['content-type' => 'application/json']
    )
    ->withFileResponse(
            file: __DIR__ . '/fixtures/country-spain.json',
            httpStatus: 201,
            headers: ['content-type' => 'application/json']
        )
    ->build();
```