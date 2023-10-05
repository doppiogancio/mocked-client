<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Route;

use DoppioGancio\MockedClient\Route\CallbackRouteBuilder;
use GuzzleHttp\Psr7\Request;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function assert;
use function json_decode;
use function parse_str;

class CallbackRouteBuilderTest extends TestCase
{
    public function testRoute(): void
    {
        $builder = new CallbackRouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $route = $builder->withMethod('GET')
            ->withPath('/country')
            ->withStringResponse(static function (RequestInterface $request): bool {
                parse_str($request->getUri()->getQuery(), $requestParameters);

                return $requestParameters['code'] === 'AU';
            }, '{"id":"+43","code":"AU","name":"Austria"}')
            ->withStringResponse(static function (RequestInterface $request): bool {
                parse_str($request->getUri()->getQuery(), $requestParameters);

                return $requestParameters['code'] === 'IT';
            }, '{"id":"+39","code":"IT","name":"Italy"}')
            //->withStringResponse('{"id":"+39","code":"IT","name":"Italy"}')
            ->build();

        // Request #1
        $response = $route->getHandler()(new Request('GET', '/country?code=AU'));
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Austria', $data['name']);

        // Request #2
        $response = $route->getHandler()(new Request('GET', '/country?code=IT'));
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Italy', $data['name']);
//        // Request #3 - Called too many times
//        $this->expectException(TooManyConsecutiveCalls::class);
//        $route->getHandler()(new Request('GET', '/country'));
    }
}
