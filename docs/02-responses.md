# Building responses

`reply()` takes a `MockResponse`, built by one of the factories below. Any response pairs with any matcher.

Responses are **rebuilt for every request**, body stream included. A route called twice hands out two independent, fully readable responses, so `getContents()` on the second call returns the body rather than an empty string.

## A string

```php
$mock->get('/a')->reply(MockResponse::text('hello'));
$mock->get('/b')->reply(MockResponse::text('hello', 201, ['x-name' => 'x-value']));
```

## JSON

```php
$mock->get('/country/IT')->reply(MockResponse::json(['id' => '+39', 'code' => 'IT', 'name' => 'Italy']));
$mock->post('/orders')->reply(MockResponse::json(['id' => 1], 201));
```

`content-type: application/json` is set unless you pass your own.

## A fixture file

```php
$mock->get('/countries')->reply(MockResponse::file(__DIR__ . '/fixtures/countries.json'));
```

Files ending in `.json` get the JSON content type automatically. An unreadable file throws `FileNotFound` **on this line**, not later when the request arrives, so the stack trace points at the stub you got wrong.

## A status code only

```php
$mock->delete('/orders/1')->reply(MockResponse::status(204));
$mock->get('/teapot')->reply(MockResponse::status(418, ['x-why' => 'short and stout']));
```

## A PSR-7 response you already have

```php
use Nyholm\Psr7\Response;

$mock->get('/a')->reply(MockResponse::psr7(new Response(200, [], 'body')));
$mock->get('/b')->reply(new Response(200, [], 'body'));   // reply() accepts it directly
```

The response is copied rather than stored, so handing the same instance to several stubs is safe.

## Built from the request

```php
use Psr\Http\Message\RequestInterface;

$mock->get('/echo')->reply(MockResponse::using(
    static fn (RequestInterface $request) => MockResponse::text($request->getHeaderLine('x-request-id')),
));
```

The callback may return a `MockResponse`, a PSR-7 response, or a plain string body:

```php
$mock->get('/echo')->reply(MockResponse::using(
    static fn (RequestInterface $request) => 'you asked for ' . $request->getUri()->getPath(),
));
```

Next: [sequences and simulated failures](./03-sequences.md).
