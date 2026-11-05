<?php

declare(strict_types=1);

namespace DoppioGancio\MockedClient;

use Closure;
use DoppioGancio\MockedClient\Exception\AssertionFailed;
use DoppioGancio\MockedClient\Exception\RequestNotMatched;
use DoppioGancio\MockedClient\Guzzle\MockHandler;
use DoppioGancio\MockedClient\Httplug\MockAsyncClient;
use DoppioGancio\MockedClient\Matching\MethodPath;
use DoppioGancio\MockedClient\Psr18\MockClient;
use DoppioGancio\MockedClient\Symfony\SymfonyResponseFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function count;
use function implode;
use function sprintf;

/**
 * A fake HTTP server you describe with stubs, and then hand to the code under
 * test as whichever kind of client it expects. Every client built from the same
 * MockServer shares its stubs and its journal.
 */
final class MockServer
{
    /** @var Stub[] */
    private array $stubs = [];

    private readonly Journal $journal;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        LoggerInterface|null $logger = null,
    ) {
        $this->journal = new Journal();
        $this->logger  = $logger ?? new NullLogger();
    }

    /** Finds the PSR-17 factories through php-http/discovery. Use the constructor to pass your own. */
    public static function create(LoggerInterface|null $logger = null): self
    {
        return new self(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $logger,
        );
    }

    public function get(string $path): Stub
    {
        return $this->on('GET', $path);
    }

    public function post(string $path): Stub
    {
        return $this->on('POST', $path);
    }

    public function put(string $path): Stub
    {
        return $this->on('PUT', $path);
    }

    public function patch(string $path): Stub
    {
        return $this->on('PATCH', $path);
    }

    public function delete(string $path): Stub
    {
        return $this->on('DELETE', $path);
    }

    public function head(string $path): Stub
    {
        return $this->on('HEAD', $path);
    }

    public function options(string $path): Stub
    {
        return $this->on('OPTIONS', $path);
    }

    /** @param string $path a literal path, or a pattern such as "/country/{code}" or "/orders/{id:\d+}" */
    public function on(string $method, string $path): Stub
    {
        $stub          = new Stub(new MethodPath($method, $path));
        $this->stubs[] = $stub;

        return $stub;
    }

    /**
     * The framework agnostic core: a PSR-7 request in, the PSR-7 response of
     * the first matching stub out.
     *
     * @throws RequestNotMatched
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        $this->logger->debug(
            sprintf('Request: %s %s', $request->getMethod(), $request->getUri()),
            ['request' => $request],
        );

        $mismatches = [];
        foreach ($this->stubs as $stub) {
            $reason = $stub->mismatch($request);
            if ($reason !== null) {
                $mismatches[] = ['label' => $stub->label(), 'reason' => $reason];

                continue;
            }

            try {
                $response = $stub->respondTo($request, $this->responseFactory, $this->streamFactory);
            } catch (Throwable $e) {
                // A simulated failure or an exhausted sequence is still a
                // request the code under test made: the retry tests count it.
                $this->journal->record($request, null, $stub->label());
                $this->logger->error($e->getMessage(), ['request' => $request, 'exception' => $e]);

                throw $e;
            }

            $this->journal->record($request, $response, $stub->label());

            $this->logger->debug(
                sprintf(
                    'Response: %d %s %s',
                    $response->getStatusCode(),
                    $request->getMethod(),
                    $request->getUri(),
                ),
                ['request' => $request, 'response' => $response],
            );

            return $response;
        }

        $this->journal->record($request);

        $exception = new RequestNotMatched($request, $mismatches);
        $this->logger->error($exception->getMessage(), ['request' => $request, 'exception' => $exception]);

        throw $exception;
    }

    public function psr18(): MockClient
    {
        return new MockClient($this);
    }

    /**
     * @param array<callable>     $middlewares pushed onto the handler stack, outermost last
     * @param array<string,mixed> $options     the same options you would give to new GuzzleHttp\Client()
     */
    public function guzzle(array $middlewares = [], array $options = []): GuzzleClient
    {
        $stack = HandlerStack::create($this->guzzleHandler());
        foreach ($middlewares as $middleware) {
            $stack->push($middleware);
        }

        $options['handler'] = $stack;

        return new GuzzleClient($options);
    }

    /** The bare Guzzle handler, to push onto a HandlerStack you build yourself. */
    public function guzzleHandler(): Closure
    {
        return (new MockHandler($this))->build();
    }

    public function httplug(): MockAsyncClient
    {
        return new MockAsyncClient($this);
    }

    /**
     * A callable to hand to Symfony's own MockHttpClient, which keeps this
     * package out of the business of reimplementing Symfony's response classes:
     *
     *     $client = new Symfony\Component\HttpClient\MockHttpClient($mock->symfonyCallback());
     */
    public function symfonyCallback(): Closure
    {
        return (new SymfonyResponseFactory($this))->build();
    }

    /**
     * @param string|null $path a literal path or a pattern such as "/country/{code}"
     *
     * @return RecordedCall[]
     */
    public function recorded(string|null $method = null, string|null $path = null): array
    {
        return $this->journal->calls($method, $path);
    }

    public function lastRequest(): RequestInterface|null
    {
        return $this->journal->last()?->request;
    }

    public function journal(): Journal
    {
        return $this->journal;
    }

    /** @throws AssertionFailed */
    public function assertRequested(string $method, string $path): void
    {
        if ($this->journal->count($method, $path) > 0) {
            return;
        }

        throw new AssertionFailed(sprintf(
            "Expected %s %s to have been requested, but it was not.\n%s",
            $method,
            $path,
            $this->describeJournal(),
        ));
    }

    /** @throws AssertionFailed */
    public function assertNotRequested(string $method, string $path): void
    {
        $count = $this->journal->count($method, $path);
        if ($count === 0) {
            return;
        }

        throw new AssertionFailed(sprintf(
            'Expected %s %s never to be requested, but it was requested %d time(s).',
            $method,
            $path,
            $count,
        ));
    }

    /** @throws AssertionFailed */
    public function assertRequestedTimes(int $times, string $method, string $path): void
    {
        $count = $this->journal->count($method, $path);
        if ($count === $times) {
            return;
        }

        throw new AssertionFailed(sprintf(
            "Expected %s %s to be requested %d time(s), got %d.\n%s",
            $method,
            $path,
            $times,
            $count,
            $this->describeJournal(),
        ));
    }

    /** Catches stubs that were written but never exercised, usually a sign the test drifted. */
    public function assertAllStubsUsed(): void
    {
        $unused = [];
        foreach ($this->stubs as $stub) {
            if ($stub->calls() > 0) {
                continue;
            }

            $unused[] = '  ' . $stub->label();
        }

        if ($unused === []) {
            return;
        }

        throw new AssertionFailed(sprintf(
            "%d stub(s) were never requested:\n%s",
            count($unused),
            implode("\n", $unused),
        ));
    }

    private function describeJournal(): string
    {
        $calls = $this->journal->calls();
        if ($calls === []) {
            return 'No requests were made.';
        }

        $lines = ['Requests actually made:'];
        foreach ($calls as $call) {
            $lines[] = sprintf('  %s %s', $call->method(), $call->path());
        }

        return implode("\n", $lines);
    }
}
