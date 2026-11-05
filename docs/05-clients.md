# Clients and adapters

One `MockServer`, every kind of client. They all share its stubs and its journal, so you can hand a Guzzle client to one collaborator and a PSR-18 client to another in the same test.

## PSR-18

```php
$client = $mock->psr18();   // Psr\Http\Client\ClientInterface
$response = $client->sendRequest($request);
```

No third party dependency. Every exception it throws implements `Psr\Http\Client\ClientExceptionInterface`, as PSR-18 requires, so `catch (ClientExceptionInterface $e)` in the code under test behaves the way it would against a real client.

## Guzzle

```php
$client = $mock->guzzle();
$client = $mock->guzzle($middlewares, ['base_uri' => 'https://api.example.com', 'timeout' => 1]);
```

The second argument is the option array you would give to `new GuzzleHttp\Client()`. The first is the middlewares to push onto the handler stack.

```php
use DoppioGancio\MockedClient\Guzzle\Middleware\Middleware;
use Psr\Http\Message\RequestInterface;

$addTrace = new class extends Middleware {
    protected function mapRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('x-trace', 'abc');
    }
};

$client = $mock->guzzle([$addTrace]);
```

Middlewares run before the stubs see the request, so `whereHeader('x-trace', 'abc')` matches what the middleware added. To push the handler onto a stack you build yourself:

```php
$stack = HandlerStack::create($mock->guzzleHandler());
```

## HTTPlug

```php
$client = $mock->httplug();   // Http\Client\HttpAsyncClient

$promise = $client->sendAsyncRequest($request);
$response = $promise->wait();
```

An unmatched request comes back as a rejected promise, so it surfaces on `wait()`.

## Symfony HttpClient

Symfony ships its own `MockHttpClient`; this package feeds it rather than reimplementing Symfony's response classes:

```php
use Symfony\Component\HttpClient\MockHttpClient;

$client = new MockHttpClient($mock->symfonyCallback());

$response = $client->request('GET', 'https://api.example.com/country/IT');
```

Headers and a string body are forwarded to the stubs, so `whereHeader()` and `whereJson()` work as usual.

## No client at all

```php
$response = $mock->handle($request);   // PSR-7 in, PSR-7 out
```

This is the whole core. Everything above is a thin adapter over it, and it is what you wire into your own client if none of the adapters fit.

## Injecting into a container

```php
self::getContainer()->set(ClientInterface::class, $mock->psr18());

// with eight_points/guzzle-bundle
self::getContainer()->set('eight_points_guzzle.client.my_client', $mock->guzzle());
```

Next: [recipes](./06-recipes.md).
