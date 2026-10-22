<?php

namespace Overfirmament\OverUtils\Tests;

use Exception;
use Overfirmament\OverUtils\ToolBox\RabbitMqUtil;
use PHPUnit\Framework\Attributes\Test;

class RabbitMqUtilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
}
