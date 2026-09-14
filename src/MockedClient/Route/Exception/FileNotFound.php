<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Route\Exception;

use Exception;

use function sprintf;

class FileNotFound extends Exception
{
    public function __construct(string $file)
    {
        parent::__construct(sprintf('File not found: %s', $file));
    }
}
