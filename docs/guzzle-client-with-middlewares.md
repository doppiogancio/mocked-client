## Guzzle client with middlewares

`MockedClient::guzzleClient()` accepts the middlewares to push onto the Guzzle `HandlerStack`, and the same `$options` array you'd pass to `new GuzzleHttp\Client($options)`.

```php
use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use DoppioGancio\MockedClient\MockedClient;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

$dummyMiddleware = new class ('x-name', 'x-value') extends Middleware {
    public function __construct(private readonly string $header, private readonly string $value)
    {
    }

    protected function mapRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->header, $this->value);
    }
};

$mockedClient = MockedClient::create();
$mockedClient->get('/middleware')
    // the middleware above adds the "x-name" header before the route sees the request
    ->respondUsing(fn (RequestInterface $request) => new Response(200, [], $request->getHeader('x-name')[0]));

$client = $mockedClient->guzzleClient([$dummyMiddleware]);

$client->request('GET', '/middleware'); // body: "x-value"
```
