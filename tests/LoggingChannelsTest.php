<?php

namespace Overfirmament\OverUtils\Tests;

use Overfirmament\OverUtils\Console\Support\LoggingChannels;
use Overfirmament\OverUtils\Logger\LogFormatter;
use PHPUnit\Framework\Attributes\Test;

class LoggingChannelsTest extends TestCase
{
    #[Test]
    public function it_materializes_channels_from_the_stub(): void
    {
        $channels = LoggingChannels::channels();

        $this->assertSame(['http_out', 'http_in', 'rabbit_mq'], array_keys($channels));
        $this->assertSame('daily', $channels['http_out']['driver']);
        $this->assertSame(storage_path('logs/http/out_info.log'), $channels['http_out']['path']);
        $this->assertSame(14, $channels['http_out']['days']);
        $this->assertSame(LogFormatter::class, $channels['http_out']['formatter']);
    }

    #[Test]
    public function the_snippets_and_channels_expose_the_same_names(): void
    {
        $this->assertSame(
            array_keys(LoggingChannels::snippets()),
            array_keys(LoggingChannels::channels())
        );
    }
}
