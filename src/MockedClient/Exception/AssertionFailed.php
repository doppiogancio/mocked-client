<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use RuntimeException;

class AssertionFailed extends RuntimeException implements MockedClientException
{
}
