# Asserting what was requested

Stubbing describes what the server answers. The journal records what your code actually asked for, which is the other half of a useful mock.

Every client built from the same `MockServer` writes to the same journal.

## Assertions

```php
$mock->assertRequested('POST', '/orders');
$mock->assertNotRequested('DELETE', '/orders/1');
$mock->assertRequestedTimes(3, 'GET', '/country/{code}');
$mock->assertAllStubsUsed();
```

The path accepts the same placeholders as a stub, so `assertRequestedTimes(3, 'GET', '/country/{code}')` counts `/country/IT`, `/country/DE` and `/country/FR` together, while `'/country/IT'` counts only that one.

`assertAllStubsUsed()` catches the opposite problem: stubs that were written and never exercised, which usually means the test drifted away from the code.

A failed assertion throws `AssertionFailed` and lists the requests that were actually made:

```
Expected POST /orders to have been requested, but it was not.
Requests actually made:
  GET /country/IT
  GET /country/DE
```

## Inspecting requests directly

When an assertion is not expressive enough, read the journal yourself:

```php
$calls = $mock->recorded('POST', '/orders');

$calls[0]->jsonBody();                   // ['sku' => 'ABC', 'qty' => 2]
$calls[0]->body();                       // the raw string
$calls[0]->header('authorization');      // 'Bearer t'
$calls[0]->path();                       // '/orders'
$calls[0]->response?->getStatusCode();   // what it got back

$mock->lastRequest();                    // RequestInterface|null
$mock->journal()->unmatched();           // requests that hit no stub
```

`recorded()` with no arguments returns every call, in order.

## With PHPUnit

The assertions throw `DoppioGancio\MockedClient\Exception\AssertionFailed`, which is a plain exception: the package does not depend on PHPUnit, so it works the same under Pest, Behat or a bare script. PHPUnit reports it as an error rather than a failure. If you would rather have a native failure, wrap it:

```php
try {
    $mock->assertRequested('POST', '/orders');
} catch (AssertionFailed $e) {
    self::fail($e->getMessage());
}
```

Next: [clients and adapters](./05-clients.md).
