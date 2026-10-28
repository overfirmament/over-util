<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Overfirmament\OverUtils\Logger\LogFormatter;
use PHPUnit\Framework\Attributes\Test;

class LogFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Log::flushSharedContext();
    }

    #[Test]
    public function it_moves_the_trace_id_to_the_top_level(): void
    {
        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
            ['trace_id' => 'trace-123', 'other' => 'value'],
        ));

        $payload = $this->decode($line);

        $this->assertSame('trace-123', $payload['trace_id']);
        $this->assertSame('hello', $payload['message']);
        $this->assertArrayNotHasKey('trace_id', $payload['context']);
    }

    #[Test]
    public function it_resolves_a_custom_track_field(): void
    {
        config()->set('overutil.trace.field', 'requestId');

        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
            ['requestId' => 'req-1'],
        ));

        $payload = $this->decode($line);

        $this->assertSame('req-1', $payload['trace_id']);
        $this->assertArrayNotHasKey('requestId', $payload['context']);
    }

    #[Test]
    public function it_resolves_the_snake_case_variant_of_the_track_field(): void
    {
        config()->set('overutil.trace.field', 'requestId');

        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
            ['request_id' => 'req-2'],
        ));

        $this->assertSame('req-2', $this->decode($line)['trace_id']);
    }

    #[Test]
    public function it_uses_the_configured_output_field(): void
    {
        config()->set('overutil.trace.output', 'request_id');

        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
            ['trace_id' => 'trace-9'],
        ));

        $payload = $this->decode($line);

        $this->assertSame('trace-9', $payload['request_id']);
        $this->assertArrayNotHasKey('trace_id', $payload);
    }

    #[Test]
    public function it_reads_the_trace_id_from_the_request(): void
    {
        config()->set('overutil.trace.field', 'request_id');

        $request = Request::create('/orders', 'GET');
        $request->merge(['request_id' => 'req-request']);
        $this->app->instance('request', $request);

        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
        ));

        $this->assertSame('req-request', $this->decode($line)['trace_id']);
    }

    #[Test]
    public function record_context_wins_over_shared_context(): void
    {
        Context::add('trace_id', 'from-context');

        $formatter = new LogFormatter;
        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Info,
            'hello',
            ['trace_id' => 'from-record'],
        ));

        $this->assertSame('from-record', $this->decode($line)['trace_id']);
    }

    #[Test]
    public function it_produces_valid_json_for_gbk_strings(): void
    {
        $formatter = new LogFormatter;
        $gbk = @iconv('UTF-8', 'GBK', '中文日志');

        if ($gbk === false) {
            $this->markTestSkipped('iconv GBK conversion is unavailable.');
        }

        $line = $formatter->format(new LogRecord(
            new \DateTimeImmutable,
            'testing',
            Level::Error,
            $gbk,
        ));

        $payload = $this->decode($line);

        $this->assertArrayHasKey('message', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $line): array
    {
        $offset = strpos($line, '] ');

        $this->assertNotFalse($offset);

        $payload = json_decode(substr($line, $offset + 2), true);

        $this->assertIsArray($payload);

        return $payload;
    }
}
