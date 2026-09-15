# Matching requests

A stub starts with a method and a path, and can be narrowed with any number of `where*()` calls. All of them must pass for the stub to answer.

Stubs are tried **in registration order and the first match wins**. There is no hidden priority: the order you write is the order that applies, so the catch all goes last.

## Method and path

```php
$mock->get('/country');
$mock->post('/orders');
$mock->put('/orders/1');
$mock->patch('/orders/1');
$mock->delete('/orders/1');
$mock->head('/health');
$mock->options('/orders');
$mock->on('PURGE', '/cache');   // anything else
```

The host is ignored, so the same stubs work whether the code under test calls `/country` or `https://api.example.com/country`. A trailing slash is ignored too.

## Path placeholders

```php
$mock->get('/country/{code}')->reply(MockResponse::json(['code' => 'IT']));
$mock->get('/orders/{id:\d+}')->reply(MockResponse::status(200));
$mock->get('/reports/{year:\d{4}}')->reply(MockResponse::status(200));
```

A bare `{name}` matches one path segment. Add `:regex` after the name to constrain it, which is how you keep `/orders/{id:\d+}` from swallowing `/orders/new`.

## Query string

```php
$mock->get('/country')->whereQuery(['code' => 'it'])->reply(...);
$mock->get('/country')->whereQuery('code=it&page=2')->reply(...);
```

The array and the string form are equivalent. The match is a **subset** check: the request may carry extra parameters, and their order never matters.

## Headers

```php
$mock->get('/me')->whereHeader('authorization', 'Bearer good')->reply(MockResponse::status(200));
$mock->get('/me')->whereHeader('x-trace')->reply(MockResponse::status(200));   // presence only
$mock->get('/me')->reply(MockResponse::status(401));                           // everything else
```

Header names are case insensitive, as PSR-7 requires.

## JSON body

```php
$mock->post('/orders')->whereJson(['sku' => 'ABC'])->reply(MockResponse::status(201));
$mock->post('/orders')->whereJson(['customer' => ['id' => 7]])->reply(MockResponse::status(201));
```

Another subset check, nested arrays included, so a test can pin the one field it cares about and ignore the rest of the payload.

## Anything else

```php
use Psr\Http\Message\RequestInterface;

$mock->get('/country')
    ->whereCallback(
        static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $parameters);

            return ($parameters['code'] ?? '') === 'AU';
        },
        'country code is AU',   // shown in the failure message
    )
    ->reply(MockResponse::json($austria));
```

The optional second argument is the description printed when no stub matches. It costs one string and saves a debugging session.

## Combining them

```php
$mock->post('/orders')
    ->whereHeader('authorization', 'Bearer t')
    ->whereJson(['sku' => 'ABC'])
    ->whereQuery(['dry' => '0'])
    ->reply(MockResponse::status(201));
```

Next: [building responses](./02-responses.md).
