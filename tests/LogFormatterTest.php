<?php

namespace Overfirmament\OverUtils\Tests;

use Monolog\Level;
use Monolog\LogRecord;
use Overfirmament\OverUtils\Logger\LogFormatter;
use PHPUnit\Framework\Attributes\Test;

class LogFormatterTest extends TestCase
{
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
