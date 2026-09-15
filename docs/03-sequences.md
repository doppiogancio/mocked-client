# Sequences and simulated failures

## A different response on each call

```php
$mock->get('/jobs/1')->reply(MockResponse::sequence(
    MockResponse::json(['status' => 'pending']),
    MockResponse::json(['status' => 'pending']),
    MockResponse::json(['status' => 'done']),
));
```

Each request consumes the next response. The call after the last one throws `TooManyCalls`, whose message says how many calls were made and how many the sequence defined.

Sequences are explicit: writing `reply()` twice on the same stub does not silently build a queue, it simply replaces the response. If you want a queue, ask for one.

Any response type can go in a sequence, so you can mix them:

```php
$mock->get('/flaky')->reply(MockResponse::sequence(
    MockResponse::failure('connection reset'),
    MockResponse::status(503),
    MockResponse::json(['ok' => true]),
));
```

## Simulating a transport failure

```php
$mock->get('/flaky')->reply(MockResponse::failure('connection timed out'));
```

This throws `NetworkFailure`, which implements `Psr\Http\Client\NetworkExceptionInterface`, so the code under test sees exactly what a real broken connection looks like rather than an HTTP error response. It is the way to exercise retry loops, backoff and circuit breakers.

The sequence above is the canonical retry test: fail, fail, then succeed, and assert the client ended up with the success and called the endpoint three times.

```php
$mock->assertRequestedTimes(3, 'GET', '/flaky');
```

Next: [asserting what was requested](./04-assertions.md).
