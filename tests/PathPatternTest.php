<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests;

use DoppioGancio\MockedClient\Matching\PathPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathPatternTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> */
    public static function pathProvider(): array
    {
        return [
            'literal match' => ['/country', '/country', true],
            'literal mismatch' => ['/country', '/countries', false],
            'placeholder' => ['/country/{code}', '/country/IT', true],
            'placeholder does not span segments' => ['/country/{code}', '/country/IT/regions', false],
            'placeholder needs a value' => ['/country/{code}', '/country/', false],
            'two placeholders' => ['/a/{x}/b/{y}', '/a/1/b/2', true],
            'constrained digits' => ['/orders/{id:\d+}', '/orders/42', true],
            'constrained rejects letters' => ['/orders/{id:\d+}', '/orders/abc', false],
            'quantifier in constraint' => ['/y/{year:\d{4}}', '/y/2026', true],
            'quantifier rejects short' => ['/y/{year:\d{4}}', '/y/26', false],
            'regex chars are literal' => ['/a.b', '/axb', false],
            'trailing slash ignored' => ['/country', '/country/', true],
            'missing leading slash' => ['country', '/country', true],
            'root' => ['/', '/', true],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testMatching(string $pattern, string $path, bool $expected): void
    {
        $this->assertSame($expected, (new PathPattern($pattern))->matches($path));
    }

    public function testExtractsParameters(): void
    {
        $pattern = new PathPattern('/country/{code}/city/{city}');

        $this->assertSame(
            ['code' => 'IT', 'city' => 'Roma'],
            $pattern->parameters('/country/IT/city/Roma'),
        );
    }

    public function testReturnsNoParametersWhenItDoesNotMatch(): void
    {
        $this->assertSame([], (new PathPattern('/country/{code}'))->parameters('/other'));
    }
}
