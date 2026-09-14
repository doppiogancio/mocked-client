## Route with a callback-matched response

Respond based on any property of the request, not just the query string, by giving `respondIf()` a callback that inspects the `Psr\Http\Message\RequestInterface` and returns a boolean. Callbacks are checked in the order they're added; the first match wins.

```php
use DoppioGancio\MockedClient\MockedClient;
use Psr\Http\Message\RequestInterface;

$mockedClient = MockedClient::create();

$mockedClient->get('/country')
    ->respondIf($this->hasCountryCode('AU'), '{"id":"+43","code":"AU","name":"Austria"}')
    ->respondIf($this->hasCountryCode('IT'), '{"id":"+39","code":"IT","name":"Italy"}');

$client = $mockedClient->guzzleClient();

$client->request('GET', '/country?code=AU'); // Austria
$client->request('GET', '/country?code=IT'); // Italy
$client->request('GET', '/country');         // throws ResponseNotFound: no callback matched

/** @return callable(RequestInterface):bool */
function hasCountryCode(string $countryCode): callable
{
    return static function (RequestInterface $request) use ($countryCode): bool {
        parse_str($request->getUri()->getQuery(), $parameters);

        return ($parameters['code'] ?? '') === $countryCode;
    };
}
```
