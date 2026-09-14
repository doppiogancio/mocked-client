<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Route\Exception\FileNotFound;
use DoppioGancio\MockedClient\Route\Exception\ResponseNotFound;
use DoppioGancio\MockedClient\Route\Exception\TooManyConsecutiveCalls;
use DoppioGancio\MockedClient\RouteExpectation;
use GuzzleHttp\Psr7\Request;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function json_decode;

class RouteExpectationTest extends TestCase
{
    public function testStaticResponseIsAlwaysReturned(): void
    {
        $expectation = $this->expectation()->respondWith('{"code":"IT"}');

        $first  = $expectation->handle(new Request('GET', '/country'));
        $second = $expectation->handle(new Request('GET', '/country'));

        $this->assertEquals('{"code":"IT"}', (string) $first->getBody());
        $this->assertEquals('{"code":"IT"}', (string) $second->getBody());
    }

    public function testRespondWithJson(): void
    {
        $expectation = $this->expectation()->respondWithJson(['code' => 'IT']);

        $response = $expectation->handle(new Request('GET', '/country'));

        $this->assertEquals(['code' => 'IT'], json_decode((string) $response->getBody(), true));
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
    }

    public function testConsecutiveResponsesAreConsumedInOrderThenThrow(): void
    {
        $expectation = $this->expectation()
            ->respondWith('{"name":"Austria"}')
            ->respondWith('{"name":"Italy"}');

        $first  = $expectation->handle(new Request('GET', '/country'));
        $second = $expectation->handle(new Request('GET', '/country'));

        $this->assertEquals('{"name":"Austria"}', (string) $first->getBody());
        $this->assertEquals('{"name":"Italy"}', (string) $second->getBody());

        $this->expectException(TooManyConsecutiveCalls::class);
        $expectation->handle(new Request('GET', '/country'));
    }

    public function testRespondWhenMatchesQueryStringWithDefaultFallback(): void
    {
        $expectation = $this->expectation()
            ->respondWhen('code=it', '{"code":"IT"}')
            ->respondWhen('code=de', '{"code":"DE"}')
            ->respondWithFile(__DIR__ . '/Route/fixtures/countries.json');

        $matched  = $expectation->handle(new Request('GET', '/country?nonce=12345&code=it&page=2'));
        $fallback = $expectation->handle(new Request('GET', '/country?code=fr'));

        $this->assertEquals('{"code":"IT"}', (string) $matched->getBody());
        $this->assertCount(2, json_decode((string) $fallback->getBody(), true));
    }

    public function testRespondWhenSupportsArrayNotationInQueryString(): void
    {
        $expectation = $this->expectation()
            ->respondWhen('filters%5BhasContent%5D=0', '{}')
            ->respondWhenFile('filters%5BhasContent%5D=1', __DIR__ . '/Route/fixtures/api_parts_manufacturers.json');

        $response = $expectation->handle(new Request('GET', '/manufacturers?filters[hasContent]=1'));

        $data = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $data['manufacturers']);
    }

    public function testRespondWhenWithoutAMatchOrDefaultThrows(): void
    {
        $expectation = $this->expectation()->respondWhen('code=it', '{"code":"IT"}');

        $this->expectException(ResponseNotFound::class);
        $expectation->handle(new Request('GET', '/country?code=fr'));
    }

    public function testRespondIfMatchesUsingACallback(): void
    {
        $expectation = $this->expectation()
            ->respondIf($this->hasCountryCode('AU'), '{"name":"Austria"}')
            ->respondIf($this->hasCountryCode('IT'), '{"name":"Italy"}');

        $au = $expectation->handle(new Request('GET', '/country?code=AU'));
        $it = $expectation->handle(new Request('GET', '/country?code=IT'));

        $this->assertEquals('{"name":"Austria"}', (string) $au->getBody());
        $this->assertEquals('{"name":"Italy"}', (string) $it->getBody());

        $this->expectException(ResponseNotFound::class);
        $expectation->handle(new Request('GET', '/country'));
    }

    public function testRespondUsingACustomHandler(): void
    {
        $expectation = $this->expectation()->respondUsing(
            static function (Request $request) {
                $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
                $body          = $streamFactory->createStream($request->getHeaderLine('test-header'));

                return Psr17FactoryDiscovery::findResponseFactory()->createResponse(200)->withBody($body);
            },
        );

        $request  = (new Request('GET', '/header'))->withHeader('test-header', 'test-value');
        $response = $expectation->handle($request);

        $this->assertEquals('test-value', (string) $response->getBody());
    }

    public function testRespondWithFileThrowsWhenFileIsMissing(): void
    {
        $this->expectException(FileNotFound::class);

        $this->expectation()->respondWithFile(__DIR__ . '/Route/fixtures/does-not-exist.json');
    }

    /** @return callable(RequestInterface):bool */
    private function hasCountryCode(string $countryCode): callable
    {
        return static fn (RequestInterface $request): bool => $request->getUri()->getQuery() === 'code=' . $countryCode;
    }

    private function expectation(): RouteExpectation
    {
        return new RouteExpectation(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );
    }
}
