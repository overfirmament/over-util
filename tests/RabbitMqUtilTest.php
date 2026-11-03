<?php

namespace Overfirmament\OverUtils\Tests;

use Exception;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Overfirmament\OverUtils\Logger\LogFormatter;
use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;
use Overfirmament\OverUtils\ToolBox\RabbitMqUtil;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

class RabbitMqUtilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        Log::flushSharedContext();

        config([
            'rabbitmq.connections.default' => [
                'host' => '127.0.0.1',
                'port' => 5672,
                'username' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'exchange' => ['name' => 'test-exchange', 'type' => 'direct'],
                'queue' => ['name' => 'test-queue'],
                'routing_key' => 'test',
                'message' => ['properties' => []],
                'publish' => [],
                'consumer' => [],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_a_new_lightweight_instance_each_time(): void
    {
        $this->assertNotSame(
            RabbitMqUtil::getInstance(),
            RabbitMqUtil::getInstance()
        );
    }

    #[Test]
    public function publishing_without_a_message_fails_before_connecting(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('message()');

        RabbitMqUtil::getInstance()->publish();
    }

    #[Test]
    public function closing_without_a_channel_is_safe(): void
    {
        RabbitMqUtil::getInstance()->close();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_keeps_the_connect_id_and_uses_a_new_mq_trace_id_for_each_publish(): void
    {
        $collector = $this->captureLogs();
        $channel = $this->fakeChannel();
        $this->bindManager($channel, 'connect-fixed');

        $mq = RabbitMqUtil::getInstance();

        $this->assertSame('connect-fixed', $mq->getConnectId());
        $this->assertNull($mq->getMqTraceId());

        $mq->message('first')->publish();
        $firstTrace = $mq->getMqTraceId();

        $mq->message('second')->publish();
        $secondTrace = $mq->getMqTraceId();

        $this->assertSame('connect-fixed', $mq->getConnectId());
        $this->assertNotNull($firstTrace);
        $this->assertNotNull($secondTrace);
        $this->assertNotSame($firstTrace, $secondTrace);

        $acks = $this->recordsStartingWith($collector, 'msg.ack');
        $publishes = $this->recordsStartingWith($collector, 'msg.publish');

        $this->assertCount(2, $acks);
        $this->assertCount(2, $publishes);

        foreach ($publishes as $index => $record) {
            $this->assertSame('connect-fixed', $record['context']['connect_id']);
            $this->assertSame($acks[$index]['context']['mq_trace_id'], $record['context']['mq_trace_id']);
            $this->assertStringContainsString('connect_id=connect-fixed', $record['message']);
            $this->assertStringContainsString('mq_trace_id=', $record['message']);
        }

        $this->assertSame($firstTrace, $publishes[0]['context']['mq_trace_id']);
        $this->assertSame($secondTrace, $publishes[1]['context']['mq_trace_id']);
        $this->assertNotSame(
            $publishes[0]['context']['mq_trace_id'],
            $publishes[1]['context']['mq_trace_id']
        );
    }

    #[Test]
    public function it_logs_the_same_ids_for_a_nacked_publish(): void
    {
        $collector = $this->captureLogs();
        $channel = $this->fakeChannel(nacked: true);
        $this->bindManager($channel, 'connect-fixed');

        $mq = RabbitMqUtil::getInstance();

        try {
            $mq->message('nack-me')->publish();
            $this->fail('A nack must throw an exception.');
        } catch (Exception $e) {
            $this->assertStringContainsString('nack', $e->getMessage());
        }

        $nacks = $this->recordsStartingWith($collector, 'msg.nack');

        $this->assertCount(1, $nacks);
        $this->assertSame('connect-fixed', $nacks[0]['context']['connect_id']);
        $this->assertSame($mq->getMqTraceId(), $nacks[0]['context']['mq_trace_id']);
        $this->assertStringContainsString('mq_trace_id=', $nacks[0]['message']);
    }

    #[Test]
    public function the_log_formatter_does_not_promote_the_mq_trace_id_to_the_application_trace(): void
    {
        $formatter = new LogFormatter;

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'rabbit_mq',
            level: Level::Info,
            message: 'msg.publish',
            context: ['connect_id' => 'connect-fixed', 'mq_trace_id' => 'mq-trace-fixed'],
            extra: [],
        );

        $line = $formatter->format($record);
        $decoded = json_decode(substr($line, (int) strpos($line, '] ') + 2), true);

        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('trace_id', $decoded);
        $this->assertSame('connect-fixed', $decoded['context']['connect_id']);
        $this->assertSame('mq-trace-fixed', $decoded['context']['mq_trace_id']);
    }

    /**
     * @return MockInterface&AMQPChannel
     */
    private function fakeChannel(bool $nacked = false): AMQPChannel
    {
        $channel = Mockery::mock(AMQPChannel::class);

        $channel->shouldReceive('is_open')->andReturnTrue();
        $channel->shouldReceive('confirm_select')->andReturnNull();
        $channel->shouldReceive('set_ack_handler')->andReturnUsing(
            static function (callable $handler) use (&$ackHandler): void {
                $ackHandler = $handler;
            }
        );
        $channel->shouldReceive('set_nack_handler')->andReturnUsing(
            static function (callable $handler) use (&$nackHandler): void {
                $nackHandler = $handler;
            }
        );
        $channel->shouldReceive('basic_publish')->andReturnUsing(
            static function (AMQPMessage $message) use (&$published): void {
                $published = $message;
            }
        );
        $channel->shouldReceive('wait_for_pending_acks')->andReturnUsing(
            static function () use (&$ackHandler, &$nackHandler, &$published, $nacked): void {
                if ($nacked) {
                    $nackHandler($published);

                    return;
                }

                $ackHandler($published);
            }
        );
        $channel->shouldReceive('close')->andReturnNull();

        return $channel;
    }

    private function bindManager(AMQPChannel $channel, string $connectId): void
    {
        $manager = Mockery::mock(RabbitMqConnectionManager::class);
        $manager->shouldReceive('connectionId')->andReturn($connectId);
        $manager->shouldReceive('channel')->andReturn($channel);

        $this->app->instance(RabbitMqConnectionManager::class, $manager);
    }

    private function captureLogs(): RabbitMqLogCollector
    {
        $collector = new RabbitMqLogCollector;

        Log::shouldReceive('channel')->with('rabbit_mq')->andReturn($collector->logger());

        return $collector;
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    private function recordsStartingWith(RabbitMqLogCollector $collector, string $prefix): array
    {
        return array_values(array_filter(
            $collector->records,
            static fn (array $record): bool => str_starts_with($record['message'], $prefix)
        ));
    }
}

class RabbitMqLogCollector
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
