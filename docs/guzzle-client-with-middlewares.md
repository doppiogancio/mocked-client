## Guzzle Client with middlewares

```php
$clientBuilder = new ClientBuilder($handlerBuilder);

$dummyMiddleware = new class ('x-name', 'x-value') extends Middleware {
    public function __construct(private readonly string $header, private readonly string $value)
    {
    }

    protected function mapRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->header, $this->value);
    }
}

$clientBuilder->addMiddleware($dummyMiddleware);

return $clientBuilder->build();
```