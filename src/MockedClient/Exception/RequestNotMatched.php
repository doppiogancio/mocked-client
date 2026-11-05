<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

use function count;
use function max;
use function sprintf;
use function str_pad;
use function strlen;

/**
 * No stub accepted the request. The message is the package's main debugging
 * tool: it prints the request as received and, for every registered stub,
 * the reason it did not match.
 */
class RequestNotMatched extends RuntimeException implements MockedClientException, RequestExceptionInterface
{
    /** @param array<array{label: string, reason: string}> $mismatches */
    public function __construct(private readonly RequestInterface $request, array $mismatches = [])
    {
        parent::__construct(self::describe($request, $mismatches));
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /** @param array<array{label: string, reason: string}> $mismatches */
    private static function describe(RequestInterface $request, array $mismatches): string
    {
        $message = sprintf(
            "No stub matched %s %s\n\nRequest as received:\n  %s %s\n",
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getMethod(),
            (string) $request->getUri(),
        );

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $message .= sprintf("  %s: %s\n", $name, $value);
            }
        }

        $body = (string) $request->getBody();
        if ($body !== '') {
            $message .= sprintf("  %s\n", $body);
        }

        if ($mismatches === []) {
            return $message . "\nNo stubs are registered on this MockServer.";
        }

        $width = 0;
        foreach ($mismatches as $mismatch) {
            $width = max($width, strlen($mismatch['label']));
        }

        $message .= sprintf("\n%d stub(s) registered:\n", count($mismatches));
        foreach ($mismatches as $mismatch) {
            $message .= sprintf("  %s  %s\n", str_pad($mismatch['label'], $width), $mismatch['reason']);
        }

        return $message;
    }
}
