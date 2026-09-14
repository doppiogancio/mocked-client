## Route with a conditional response

Respond differently depending on the request's query string. `respondWhen()` calls are checked in the order they're added; the first match wins. A plain `respondWith*()` call acts as the fallback when nothing matches.

```php
use DoppioGancio\MockedClient\MockedClient;

require_once "vendor/autoload.php";

$mockedClient = MockedClient::create();

$mockedClient->get('/country')
    ->respondWhen('page=2&code=it', '{"id":"+39","code":"IT","name":"Italy"}', httpStatus: 201)
    ->respondWhen('code=de', '{"id":"+49","code":"DE","name":"Germany"}', httpStatus: 301)
    ->respondWhenFile('code=fr', __DIR__ . '/fixtures/country.json')
    ->respondWithFile(__DIR__ . '/fixtures/countries.json'); // fallback

$client = $mockedClient->guzzleClient();

$client->request('GET', '/country?code=it&page=2'); // 201, Italy
$client->request('GET', '/country?code=xx');        // 200, the full countries.json list
```

If you don't register a fallback and nothing matches, the route throws `DoppioGancio\MockedClient\Route\Exception\ResponseNotFound`.
