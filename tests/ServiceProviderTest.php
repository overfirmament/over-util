<?php

namespace Overfirmament\OverUtils\Tests;

use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_default_config(): void
    {
        $this->assertIsArray(config('overutil.logging.channels'));
        $this->assertArrayHasKey('http_out', config('overutil.logging.channels'));
        $this->assertArrayHasKey('default', config('alarm.qy_wx'));
        $this->assertArrayHasKey('default', config('rabbitmq.connections'));
    }

    #[Test]
    public function it_registers_runtime_logging_fallbacks(): void
    {
        $channel = config('logging.channels.http_out');

        $this->assertIsArray($channel);
        $this->assertSame('daily', $channel['driver']);
        $this->assertSame(storage_path('logs/http/out_info.log'), $channel['path']);
    }

    #[Test]
    public function it_binds_the_rabbitmq_connection_manager_as_a_singleton(): void
    {
        $first = app(RabbitMqConnectionManager::class);
        $second = app(RabbitMqConnectionManager::class);

        $this->assertSame($first, $second);
    }
}
