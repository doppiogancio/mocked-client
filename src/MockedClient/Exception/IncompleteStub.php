<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use LogicException;

use function sprintf;

class IncompleteStub extends LogicException implements MockedClientException
{
    public function __construct(string $method, string $path)
    {
        parent::__construct(sprintf(
            'The stub for %s %s has no response: finish it with ->reply(MockResponse::...).',
            $method,
            $path,
        ));
    }
}
