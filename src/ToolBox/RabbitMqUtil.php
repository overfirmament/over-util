<?php

namespace Overfirmament\OverUtils\ToolBox;

use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class RabbitMqUtil
{
    protected string $configName;

    protected ?AMQPChannel $channel = null;

    protected ?AMQPMessage $msg = null;

    protected ?string $queueName = null;

    protected ?string $exchangeName = null;

    protected bool $isAck = true;

    protected bool $confirmSelected = false;

    /** @var array<string, mixed> */
    protected array $config;

    protected string $uuid;

    protected string $routingKey = '';

    /**
     * Returns a lightweight producer/consumer object. AMQP connections are
     * cached by RabbitMqConnectionManager, so creating this object in a loop is
     * safe and does not reconnect for every message.
     *
     * @throws Exception
     */
    public static function getInstance(string $configName = 'default'): static
    {
        return new static($configName);
    }

    /**
     * @throws Exception
     */
    protected function __construct(string $configName)
    {
        $this->configName = $configName;
        $this->config = (array) config("rabbitmq.connections.{$configName}");

        if ($this->config === []) {
            throw new Exception("rabbitmq配置{$configName}不存在");
        }

        $this->uuid = str_replace('-', '', (string) \Str::uuid());
        $this->log('info', "mqTool初始化 {$this->uuid}，使用配置rabbitmq.connections.{$configName}", [
            'config' => $this->safeConfig(),
        ]);
    }

    public function setRoutingKey(string $routingKey): static
    {
        $this->routingKey = $routingKey;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function autoBuild(): static
    {
        try {
            $this->exchange();
            $this->queue();
            $this->bind();
        } catch (Throwable $e) {
            $this->closeChannel();

            throw $e;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function exchange(): static
    {
        $exchange = (array) ($this->config['exchange'] ?? []);

        if (($exchange['name'] ?? '') === '') {
            throw new Exception('exchange 配置缺失：rabbitmq.exchange.name');
        }

        $this->exchangeName = (string) $exchange['name'];

        $this->channel()->exchange_declare(
            $this->exchangeName,
            (string) ($exchange['type'] ?? 'direct'),
            (bool) ($exchange['passive'] ?? false),
            (bool) ($exchange['durable'] ?? false),
            (bool) ($exchange['auto_delete'] ?? false),
            (bool) ($exchange['internal'] ?? false),
            (bool) ($exchange['nowait'] ?? false),
            $exchange['arguments'] ?? null,
            $exchange['ticket'] ?? null
        );

        $this->log('info', "exchange构建完成 {$this->uuid}");

        return $this;
    }

    /**
     * @throws Exception
     */
    public function queue(): static
    {
        $queue = (array) ($this->config['queue'] ?? []);

        if (($queue['name'] ?? '') === '') {
            throw new Exception('queue 配置缺失：rabbitmq.queue.name');
        }

        $this->queueName = (string) $queue['name'];

        $this->channel()->queue_declare(
            $this->queueName,
            (bool) ($queue['passive'] ?? false),
            (bool) ($queue['durable'] ?? false),
            (bool) ($queue['exclusive'] ?? false),
            (bool) ($queue['auto_delete'] ?? true),
            (bool) ($queue['nowait'] ?? false),
            $queue['arguments'] ?? null,
            $queue['ticket'] ?? null
        );

        $this->log('info', "queue构建完成 {$this->uuid}");

        return $this;
    }

    /**
     * @throws Exception
     */
    public function bind(): static
    {
        if ($this->queueName === null || $this->exchangeName === null) {
            throw new Exception('绑定失败：请先调用 exchange() 和 queue()，或直接使用 autoBuild()');
        }

        $routingKey = $this->routingKey !== ''
            ? $this->routingKey
            : (string) ($this->config['routing_key'] ?? '');

        $this->channel()->queue_bind($this->queueName, $this->exchangeName, $routingKey);
        $this->log('info', "bind构建完成 {$this->uuid}");

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function message(string $string, array $properties = []): static
    {
        $messageConfig = (array) ($this->config['message'] ?? []);
        $properties = array_merge((array) ($messageConfig['properties'] ?? []), $properties);

        $this->msg = new AMQPMessage($string, $properties);
        $this->log('info', "message构建完成 {$this->uuid}");

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function publish(): void
    {
        if (! $this->msg instanceof AMQPMessage) {
            throw new Exception('发布失败：请先调用 message() 设置消息内容');
        }

        $publish = (array) ($this->config['publish'] ?? []);
        $channel = $this->channel();
        $nacked = false;

        try {
            if ($this->isAck) {
                $this->enableConfirmMode($channel);
                $channel->set_ack_handler(function (AMQPMessage $message): void {
                    $this->log('info', "msg.ack {$this->uuid} ".$message->body);
                });
                $channel->set_nack_handler(function (AMQPMessage $message) use (&$nacked): void {
                    $nacked = true;
                    $this->log('error', "msg.nack {$this->uuid} ".$message->body);
                });
            }

            $routingKey = $this->routingKey !== ''
                ? $this->routingKey
                : (string) ($this->config['routing_key'] ?? '');

            $channel->basic_publish(
                $this->msg,
                $this->exchangeName ?? '',
                $routingKey,
                (bool) ($publish['mandatory'] ?? false),
                (bool) ($publish['immediate'] ?? false),
                $publish['ticket'] ?? null
            );

            if ($this->isAck) {
                $channel->wait_for_pending_acks((float) ($publish['confirm_timeout'] ?? 5.0));
            }

            if ($nacked) {
                throw new Exception('RabbitMQ 返回 nack，消息未被 broker 确认');
            }

            $this->log('info', "msg.publish {$this->uuid}");
        } finally {
            $this->closeChannel();

            if (($publish['close_connection_after_publish'] ?? false) === true) {
                app(RabbitMqConnectionManager::class)->disconnect($this->configName);
            }
        }
    }

    public function isAck(bool $isAck): static
    {
        $this->isAck = $isAck;

        return $this;
    }

    /**
     * Closes the channel only. The underlying connection stays in the manager
     * cache for reuse by the next message.
     */
    public function close(): void
    {
        $this->closeChannel();
        $this->log('info', "mqTool关闭channel {$this->uuid}");
    }

    /**
     * Explicitly closes the shared connection for this config name.
     */
    public function closeConnection(): void
    {
        $this->closeChannel();
        app(RabbitMqConnectionManager::class)->disconnect($this->configName);
        $this->log('info', "mqTool关闭connection {$this->uuid}");
    }

    /**
     * @throws Throwable
     */
    public function consume(callable $callback): void
    {
        if ($this->queueName === null) {
            throw new Exception('消费失败：请先调用 queue() 或 autoBuild()');
        }

        $consumer = (array) ($this->config['consumer'] ?? []);
        $channel = $this->channel();

        try {
            $channel->basic_consume(
                $this->queueName,
                (string) ($consumer['tag'] ?? ''),
                (bool) ($consumer['no_local'] ?? false),
                (bool) ($consumer['no_ack'] ?? false),
                (bool) ($consumer['exclusive'] ?? false),
                (bool) ($consumer['nowait'] ?? false),
                $callback,
                $consumer['ticket'] ?? null,
                $consumer['arguments'] ?? []
            );

            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $this->closeChannel();
        }
    }

    /**
     * @throws Exception
     */
    private function channel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->channel = app(RabbitMqConnectionManager::class)
            ->channel($this->configName, $this->config);
        $this->confirmSelected = false;

        return $this->channel;
    }

    private function enableConfirmMode(AMQPChannel $channel): void
    {
        if ($this->confirmSelected) {
            return;
        }

        $channel->confirm_select();
        $this->confirmSelected = true;
    }

    private function closeChannel(): void
    {
        if (! $this->channel instanceof AMQPChannel) {
            return;
        }

        try {
            if ($this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (Throwable $e) {
            $this->log('warning', "mqTool关闭channel失败 {$this->uuid}", [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->channel = null;
            $this->confirmSelected = false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('rabbit_mq')->log($level, $message, $context);
        } catch (Throwable) {
            try {
                Log::log($level, $message, $context);
            } catch (Throwable) {
                // Logging must never break publishing.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function safeConfig(): array
    {
        $config = $this->config;

        unset($config['password'], $config['login_response']);

        return $config;
    }
}
