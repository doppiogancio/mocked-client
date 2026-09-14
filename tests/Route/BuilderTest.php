<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Tests\Route;

use DoppioGancio\MockedClient\Route\Exception\FileNotFound;
use DoppioGancio\MockedClient\Route\RouteBuilder;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function testMissingFileThrowsFileNotFound(): void
    {
        $builder = new RouteBuilder(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
        );

        $this->expectException(FileNotFound::class);

        $builder
            ->withMethod('GET')
            ->withPath('/missing')
            ->withFileResponse(__DIR__ . '/fixtures/does-not-exist.json')
            ->build();
    }
}
