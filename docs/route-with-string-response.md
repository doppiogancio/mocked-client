## Route with a string or file response

```php
use DoppioGancio\MockedClient\MockedClient;

require_once "vendor/autoload.php";

$mockedClient = MockedClient::create();

// Route with a string body
$mockedClient->get('/country/FR')
    ->respondWith(
        content: '{"id":"+33","code":"FR","name":"France"}',
        httpStatus: 201,
        headers: ['content-type' => 'application/json'],
    );

// ...or with a shortcut for JSON
$mockedClient->get('/country/FR')
    ->respondWithJson(['id' => '+33', 'code' => 'FR', 'name' => 'France'], httpStatus: 201);

// Route with a file body
$mockedClient->get('/country/DE')
    ->respondWithFile(
        file: __DIR__ . '/fixtures/country.json',
        httpStatus: 201,
        headers: ['content-type' => 'application/json'],
    );

$client = $mockedClient->guzzleClient();
```
