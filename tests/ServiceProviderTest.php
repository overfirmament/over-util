<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Support\ServiceProvider;
use Overfirmament\OverUtils\OverUtilServiceProvider;
use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_default_config(): void
    {
        $this->assertIsArray(config('logchannel.channels'));
        $this->assertArrayHasKey('http_out', config('logchannel.channels'));
        $this->assertNull(config('overutil.logging'));
        $this->assertSame('trace_id', config('overutil.trace.field'));
        $this->assertSame('trace_id', config('overutil.trace.output'));
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
    public function it_does_not_publish_the_internal_logchannel_config(): void
    {
        $paths = ServiceProvider::$publishes[OverUtilServiceProvider::class] ?? [];

        $this->assertContains(config_path('overutil.php'), $paths);
        $this->assertNotContains(config_path('logchannel.php'), $paths);
    }

    #[Test]
    public function it_binds_the_rabbitmq_connection_manager_as_a_singleton(): void
    {
        $first = app(RabbitMqConnectionManager::class);
        $second = app(RabbitMqConnectionManager::class);

        $this->assertSame($first, $second);
    }
}
