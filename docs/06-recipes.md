# Recipes

## Testing a retry with backoff

Fail twice, then succeed, and assert the client kept trying.

```php
$mock->get('/flaky')->reply(MockResponse::sequence(
    MockResponse::failure('connection reset'),
    MockResponse::failure('connection reset'),
    MockResponse::json(['ok' => true]),
));

$result = (new ResilientApiClient($mock->psr18()))->fetch();

$this->assertTrue($result['ok']);
$mock->assertRequestedTimes(3, 'GET', '/flaky');
```

## Testing pagination

```php
$mock->get('/items')->whereQuery(['page' => '1'])->reply(MockResponse::json(['items' => [1, 2], 'next' => 2]));
$mock->get('/items')->whereQuery(['page' => '2'])->reply(MockResponse::json(['items' => [3], 'next' => null]));

$all = (new ItemFeed($mock->psr18()))->all();

$this->assertSame([1, 2, 3], $all);
$mock->assertAllStubsUsed();   // the feed really did follow the cursor
```

## Testing that a token is sent

```php
$mock->get('/me')->whereHeader('authorization', 'Bearer secret')->reply(MockResponse::json(['id' => 1]));
$mock->get('/me')->reply(MockResponse::status(401));

$client = new ApiClient($mock->psr18(), token: 'secret');

$this->assertSame(1, $client->me()['id']);
```

The unauthorised fallback matters: without it the test would fail with "no stub matched" whatever went wrong, instead of showing you a 401 and the real bug.

## Asserting the payload you sent

```php
$mock->post('/orders')->reply(MockResponse::status(201));

(new OrderApi($mock->psr18()))->place(sku: 'ABC', quantity: 2);

$sent = $mock->recorded('POST', '/orders')[0];

$this->assertSame(['sku' => 'ABC', 'qty' => 2], $sent->jsonBody());
$this->assertSame('application/json', $sent->header('content-type'));
```

## Testing error handling

```php
$mock->get('/country/XX')->reply(MockResponse::status(404));
$mock->get('/country/YY')->reply(MockResponse::text('<html>oops</html>', 500));
$mock->get('/country/ZZ')->reply(MockResponse::failure('timed out'));
```

Three different failure shapes: a clean 404, a server error whose body is not the JSON your parser expects, and a connection that never completed. Production code usually handles one of them and falls over on the other two.

## Sharing a server across a test case

```php
protected function setUp(): void
{
    $this->mock = MockServer::create();
    $this->mock->get('/health')->reply(MockResponse::status(200));
}
```

Build one per test. A `MockServer` carries the sequence cursors and the journal, so reusing one across tests leaks state between them.

## Finding out which stubs you need

Write no stubs, run the test, read the failure. It names the request and, once you have some stubs, why each one refused it. Add exactly the stub it asks for and repeat.
