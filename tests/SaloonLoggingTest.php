<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Mockery;
use Overfirmament\OverUtils\Http\Saloon\Middleware\LogSaloonFatal;
use Overfirmament\OverUtils\Http\Saloon\SaloonLogger;
use Overfirmament\OverUtils\OverUtilServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Config as SaloonConfig;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class SaloonLoggingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('overutil.saloon', [
            'logging' => [
                'enabled' => true,
                'channel' => 'http_out',
                'level' => 'info',
                'log_request' => true,
                'log_response' => true,
                'log_fatal' => true,
                'log_headers' => false,
                'log_body' => true,
                'max_body_length' => 2000,
                'redacted_headers' => ['authorization', 'cookie'],
                'redacted_keys' => ['password', 'access_token'],
                'connectors' => [],
                'except_connectors' => [],
            ],
            'trace' => [
                'header' => 'X-Request-Id',
                'propagate' => true,
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Log::flushSharedContext();

        config()->set('overutil.saloon.logging.enabled', true);
    }

    protected function tearDown(): void
    {
        $this->removeSaloonPipes();

        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_logs_the_request_and_response(): void
    {
        $collector = $this->captureLogs();

        $this->sendRequest();

        $this->assertCount(2, $collector->records);

        $request = $collector->records[0];
        $this->assertSame('info', $request['level']);
        $this->assertSame('saloon.request', $request['message']);
        $this->assertSame('POST', $request['context']['method']);
        $this->assertSame('https://api.example.test/orders?page=1', $request['context']['url']);
        $this->assertSame(SaloonLoggingConnector::class, $request['context']['connector']);
        $this->assertSame(SaloonLoggingRequest::class, $request['context']['request']);
        $this->assertArrayNotHasKey('headers', $request['context']);
        $this->assertSame(['sku' => 'A001', 'password' => '***'], $request['context']['body']);
        $this->assertArrayNotHasKey('trace_id', $request['context']);

        $response = $collector->records[1];
        $this->assertSame('saloon.response', $response['message']);
        $this->assertSame(200, $response['context']['status']);
        $this->assertIsFloat($response['context']['duration_ms']);
        $this->assertSame(['id' => 1, 'access_token' => '***'], $response['context']['body']);
    }

    #[Test]
    public function it_prefers_the_explicit_trace_header_over_context(): void
    {
        Context::add('trace_id', 'from-context');
        $collector = $this->captureLogs();

        $this->sendRequest(['X-Request-Id' => 'from-header']);

        $this->assertSame('from-header', $collector->records[0]['context']['trace_id']);
        $this->assertSame('from-header', $collector->records[1]['context']['trace_id']);
    }

    #[Test]
    public function it_falls_back_to_the_ambient_trace_context(): void
    {
        Context::add('trace_id', 'from-context');
        $collector = $this->captureLogs();

        $this->sendRequest();

        $this->assertSame('from-context', $collector->records[0]['context']['trace_id']);
        $this->assertSame('from-context', $collector->records[1]['context']['trace_id']);
    }

    #[Test]
    public function it_propagates_the_trace_header_to_the_outgoing_request(): void
    {
        config()->set('overutil.saloon.logging.log_request', false);
        config()->set('overutil.saloon.logging.log_response', false);
        Context::add('trace_id', 'ctx-prop');

        $pending = (new SaloonLoggingConnector)->createPendingRequest(new SaloonLoggingRequest);

        $this->assertSame('ctx-prop', $pending->headers()->get('X-Request-Id'));
    }

    #[Test]
    public function it_does_not_overwrite_an_explicit_trace_header(): void
    {
        config()->set('overutil.saloon.logging.log_request', false);
        config()->set('overutil.saloon.logging.log_response', false);
        Context::add('trace_id', 'ctx-prop');

        $pending = (new SaloonLoggingConnector)->createPendingRequest(
            new SaloonLoggingRequest(requestHeaders: ['X-Request-Id' => 'explicit'])
        );

        $this->assertSame('explicit', $pending->headers()->get('X-Request-Id'));
    }

    #[Test]
    public function it_does_not_log_when_disabled(): void
    {
        config()->set('overutil.saloon.logging.enabled', false);
        $collector = $this->captureLogs();

        $this->sendRequest();

        $this->assertCount(0, $collector->records);
    }

    #[Test]
    public function it_filters_connectors_with_allow_and_deny_lists(): void
    {
        $collector = $this->captureLogs();

        config()->set('overutil.saloon.logging.connectors', ['App\\Other\\*']);
        $this->sendRequest();
        $this->assertCount(0, $collector->records);

        config()->set('overutil.saloon.logging.connectors', ['Overfirmament\\OverUtils\\Tests\\*']);
        $this->sendRequest();
        $this->assertCount(2, $collector->records);

        config()->set('overutil.saloon.logging.connectors', []);
        config()->set('overutil.saloon.logging.except_connectors', [SaloonLoggingConnector::class]);
        $this->sendRequest();
        $this->assertCount(2, $collector->records);
    }

    #[Test]
    public function it_redacts_headers_and_body_keys(): void
    {
        config()->set('overutil.saloon.logging.log_headers', true);
        $collector = $this->captureLogs();

        $this->sendRequest([
            'Authorization' => 'Bearer secret',
            'X-Custom' => 'visible',
        ]);

        $headers = $collector->records[0]['context']['headers'];
        $this->assertSame('***', $headers['Authorization']);
        $this->assertSame('visible', $headers['X-Custom']);
        $this->assertSame('***', $collector->records[0]['context']['body']['password']);
    }

    #[Test]
    public function it_truncates_large_bodies(): void
    {
        config()->set('overutil.saloon.logging.max_body_length', 20);
        $collector = $this->captureLogs();

        $this->sendRequest(payload: ['description' => str_repeat('a', 100)]);

        $body = $collector->records[0]['context']['body'];

        $this->assertTrue($body['_truncated']);
        $this->assertGreaterThan(20, $body['bytes']);
        $this->assertIsString($body['preview']);
    }

    #[Test]
    public function it_summarizes_binary_responses(): void
    {
        $collector = $this->captureLogs();

        $this->sendRequest(mock: new MockClient([
            MockResponse::make('binary-data', 200, ['Content-Type' => 'application/octet-stream']),
        ]));

        $body = $collector->records[1]['context']['body'];

        $this->assertIsString($body);
        $this->assertStringContainsString('application/octet-stream', $body);
        $this->assertStringContainsString('11 bytes', $body);
    }

    #[Test]
    public function it_skips_the_request_log_when_disabled(): void
    {
        config()->set('overutil.saloon.logging.log_request', false);
        $collector = $this->captureLogs();

        $this->sendRequest();

        $this->assertCount(1, $collector->records);
        $this->assertSame('saloon.response', $collector->records[0]['message']);
    }

    #[Test]
    public function it_skips_the_response_log_when_disabled(): void
    {
        config()->set('overutil.saloon.logging.log_response', false);
        $collector = $this->captureLogs();

        $this->sendRequest();

        $this->assertCount(1, $collector->records);
        $this->assertSame('saloon.request', $collector->records[0]['message']);
    }

    #[Test]
    public function it_logs_fatal_errors(): void
    {
        $collector = $this->captureLogs();

        $pending = (new SaloonLoggingConnector)->createPendingRequest(new SaloonLoggingRequest);
        $pending->config()->add(SaloonLogger::STARTED_AT_KEY, microtime(true));

        (new LogSaloonFatal)(new FatalRequestException(new \RuntimeException('timeout'), $pending));

        $fatal = array_values(array_filter(
            $collector->records,
            static fn (array $record): bool => $record['message'] === 'saloon.fatal'
        ));

        $this->assertCount(1, $fatal);
        $record = $fatal[0];

        $this->assertSame('error', $record['level']);
        $this->assertSame('saloon.fatal', $record['message']);
        $this->assertSame(\RuntimeException::class, $record['context']['exception']);
        $this->assertSame('timeout', $record['context']['message']);
    }

    #[Test]
    public function it_falls_back_to_the_default_logger_when_the_channel_is_invalid(): void
    {
        config()->set('overutil.saloon.logging.channel', 'missing-channel');

        Log::shouldReceive('channel')->with('missing-channel')->andThrow(new \InvalidArgumentException('missing'));
        Log::shouldReceive('log')->twice()->with('info', Mockery::type('string'), Mockery::type('array'));

        $response = $this->sendRequest();

        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function it_registers_the_middleware_only_once(): void
    {
        $provider = new OverUtilServiceProvider($this->app);

        $provider->boot();
        $provider->boot();

        $names = array_map(
            static fn (object $pipe): ?string => $pipe->name,
            SaloonConfig::globalMiddleware()->getRequestPipeline()->getPipes()
        );

        $this->assertSame(1, count(array_filter($names, static fn (?string $name): bool => $name === 'overutil.saloon.request')));
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $payload
     */
    private function sendRequest(array $headers = [], ?array $payload = null, ?MockClient $mock = null): Response
    {
        $payload ??= ['sku' => 'A001', 'password' => 'secret'];

        $mock ??= new MockClient([
            MockResponse::make(
                ['id' => 1, 'access_token' => 'response-secret'],
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        return (new SaloonLoggingConnector)->send(
            new SaloonLoggingRequest(payload: $payload, requestHeaders: $headers),
            $mock
        );
    }

    private function captureLogs(): SaloonLogCollector
    {
        $collector = new SaloonLogCollector;

        Log::shouldReceive('channel')->andReturn($collector->logger());

        return $collector;
    }

    private function removeSaloonPipes(): void
    {
        $pipeline = SaloonConfig::globalMiddleware();
        $withoutSaloon = static fn (array $pipes): array => array_values(array_filter(
            $pipes,
            static fn (object $pipe): bool => ! str_starts_with((string) $pipe->name, 'overutil.saloon.')
        ));

        $pipeline->getRequestPipeline()->setPipes($withoutSaloon($pipeline->getRequestPipeline()->getPipes()));
        $pipeline->getResponsePipeline()->setPipes($withoutSaloon($pipeline->getResponsePipeline()->getPipes()));
        $pipeline->getFatalPipeline()->setPipes($withoutSaloon($pipeline->getFatalPipeline()->getPipes()));
    }
}

class SaloonLogCollector
{
    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function logger(): LoggerInterface
    {
        $logger = Mockery::mock(LoggerInterface::class);

        $logger->shouldReceive('log')->andReturnUsing(function (mixed $level, mixed $message, array $context = []): void {
            $this->records[] = [
                'level' => (string) $level,
                'message' => (string) $message,
                'context' => $context,
            ];
        });

        return $logger;
    }
}

class SaloonLoggingConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.example.test';
    }
}

class SaloonLoggingRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestHeaders
     * @param  array<string, mixed>  $requestQuery
     */
    public function __construct(
        protected array $payload = ['sku' => 'A001', 'password' => 'secret'],
        protected array $requestHeaders = [],
        protected array $requestQuery = ['page' => 1],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/orders';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->requestQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
