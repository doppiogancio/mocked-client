## Route with consecutive calls

Chain more than one `respondWith*()` call and each request consumes the next response in order. Once the queue is exhausted, the route throws `DoppioGancio\MockedClient\Route\Exception\TooManyConsecutiveCalls`.

```php
use DoppioGancio\MockedClient\MockedClient;

$mockedClient = MockedClient::create();

$mockedClient->get('/country')
    ->respondWith('{"id":"+39","code":"IT","name":"Italy"}')
    ->respondWith(
        content: '{"id":"+33","code":"FR","name":"France"}',
        httpStatus: 201,
        headers: ['content-type' => 'application/json'],
    )
    ->respondWithFile(
        file: __DIR__ . '/fixtures/country-spain.json',
        httpStatus: 201,
        headers: ['content-type' => 'application/json'],
    );

$client = $mockedClient->guzzleClient();

$client->request('GET', '/country'); // Italy
$client->request('GET', '/country'); // France
$client->request('GET', '/country'); // Spain
$client->request('GET', '/country'); // throws TooManyConsecutiveCalls
```
