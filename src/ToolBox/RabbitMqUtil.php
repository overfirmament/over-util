<?php

namespace Overfirmament\OverUtils\ToolBox;

use App\Utils\HelperUtil;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMqUtil
{
    protected static $instance = [];

    protected AMQPMessage $msg;
    protected AMQPStreamConnection $connection;
    protected string $queueName;
    protected string $exchangeName;

    protected AbstractChannel|AMQPChannel $channel;
    protected bool $isAck = true;

    protected array $config;

    protected string $uuid;

    protected string $routingKey = "";


    /**
     * @param  string  $configName
     *
     * @return static
     * @throws Exception
     */
    public static function getInstance(string $configName = "default"): static
    {
//        if (!isset(static::$instance[$configName])) {
//            static::$instance[$configName] = new static($configName);
//        }

        return new static($configName);
    }


    /**
     * @param  string  $configName
     *
     * @throws Exception
     */
    protected function __construct(string $configName)
    {
        $this->config = config("rabbitmq.connections.{$configName}");
        if (empty($this->config)){
            throw new Exception("rabbitmq配置{$configName}不存在");
        }

        $this->uuid = str_replace("-", "", \Str::uuid()->toString());
        \Log::channel("rabbit_mq")->info("mqTool初始化 {$this->uuid}，使用配置rabbitmq.connections.{$configName}", $this->config);

        $host = $this->config["host"] ?? "127.0.0.1";
        $port = $this->config["port"] ?? 5672;
        $user = $this->config["username"] ?? "guest";
        $pwd  = $this->config["password"] ?? "guest";

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pwd);
        $this->channel = $this->connection->channel();
    }


    /**
     * @param  string  $routingKey
     *
     * @return RabbitMqUtil
     */
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
        $this->exchange();
        $this->queue();
        $this->bind();

        return $this;
    }


    /**
     *|------------------------------------------------------------------------
     *|创建交换机
     *|------------------------------------------------------------------------
     *|@doc   string      $exchange       交换机名称
     *|@doc   string      $type           交换机类型
     *|@doc   bool        $passive        false 如果交换机不存在,则会抛出一个错误的异常 true 如果你希望查询交换机是否存在．而又不想在查询时创建这个交换机
     *|@doc   bool        $durable        表示了如果MQ服务器重启,这个交换机是否要重新建立 true 是 false 否
     *|@doc   bool        $auto_delete    如果绑定的所有队列都不在使用了.是否自动删除这个交换机
     *|@doc   bool        $internal       内部交换机.即不允许使用客户端推送消息.MQ内部可以让交换机作为一个队列绑定到另外一个交换机下
     *|@doc   bool        $nowait         如果为True则表示不等待服务器回执信息.函数将返回NULL,可以提高访问速度..应用范围不确定
     *|@doc   null        $arguments      额外的一些参数
     *|@doc   null        $ticket         未知
     *|------------------------------------------------------------------------
     * @return  $this
     */
    public function exchange(): static
    {
        $exchange = $this->config["exchange"];
        $this->exchangeName = $exchange["name"];
        $this->channel->exchange_declare(
            $exchange["name"],
            $exchange["type"],
            $exchange["passive"] ?? false,
            $exchange["durable"] ?? false,
            $exchange["auto_delete"] ?? false,
            $exchange["internal"] ?? false,
            $exchange["nowait"] ?? false,
            $exchange["arguments"] ?? null,
            $exchange["ticket"] ?? null
        );
        \Log::channel("rabbit_mq")->info("exchange构建完成 {$this->uuid}");

        return $this;
    }


    /**
     * @throws Exception
     */
    public function bind(): static
    {
        if (!$this->queueName || !$this->exchangeName) {
            throw new Exception("绑定失败：交换机名和队列名缺失");
        }

        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->routingKey ?:$this->config["routing_key"]);
        \Log::channel("rabbit_mq")->info("bind构建完成 {$this->uuid}");

        return $this;
    }

    /**
     *|------------------------------------------------------------------------
     *|创建队列
     *|------------------------------------------------------------------------
     *|$queue：指定要声明的队列的名称。如果未指定，则服务器将为队列生成一个随机名称。默认值为 ""。
     *|$passive：如果设置为 true，那么声明一个已存在的队列。如果队列不存在，则会抛出一个 AMQPChannelException 异常。默认为 false。
     *|$durable：如果设置为 true，则在服务器重启时也会保留该队列。默认为 false。
     *|$exclusive：如果设置为 true，则该队列将只能由声明它的连接使用。默认为 false。
     *|$auto_delete：如果设置为 true，则在最后一个消费者取消订阅时，该队列将被自动删除。默认为 true。
     *|$nowait：如果设置为 true，则不等待服务器的响应。默认为 false。
     *|$arguments：指定用于声明队列的其他参数。默认为 null。
     *|$ticket：指定用于访问队列的权限令牌。默认为 null。
     *|注意，如果 $passive 设置为 true，则 $durable 和 $auto_delete 参数的值将被忽略。
     *|------------------------------------------------------------------------
     * @return  $this
     */
    public function queue(): static
    {
        $queue = $this->config["queue"];
        $this->queueName = $queue["name"];
        $this->channel->queue_declare(
            $queue["name"] ?? "",
            $queue["passive"] ?? false,
            $queue["durable"] ?? false,
            $queue["exclusive"] ?? false,
            $queue["auto_delete"] ?? true,
            $queue["nowait"] ?? false,
            $queue["arguments"] ?? null,
            $queue["ticket"] ?? null
        );
        \Log::channel("rabbit_mq")->info("queue构建完成 {$this->uuid}");

        return $this;
    }


    /**
     * 设置 MQ 的消息
     *
     * @param  string  $string
     * @param  array  $properties
     *
     * @return $this
     */
    public function message(string $string, array $properties = []): static
    {
        $msgConf = $this->config["message"];
        $properties = array_merge($msgConf["properties"] ?? [], $properties);

        $this->msg = new AMQPMessage($string, $properties);
        \Log::channel("rabbit_mq")->info("message构建完成 {$this->uuid}");

        return $this;
    }


    /**
     * 发布
     *
     * @return void
     * @throws Throwable
     */
    public function publish(): void
    {
        $this->channel->confirm_select();
        $this->channel->set_ack_handler(
            function (AMQPMessage $message) {
                \Log::channel("rabbit_mq")->info("msg.ack {$this->uuid} ".$message->body);
            }
        );
        $this->channel->set_nack_handler(
            function (AMQPMessage $message) {
                \Log::channel("rabbit_mq")->info("msg.nack {$this->uuid} ".$message->body);
            }
        );

        $publish = $this->config["publish"];
        $this->channel->basic_publish($this->msg,
            $this->exchangeName,
            $this->routingKey ?: $this->config["routing_key"],
            $publish["mandatory"] ?? false,
            $publish["immediate"] ?? false,
            $publish["ticket"] ?? null
        );

        if($this->isAck){
            $this->channel->wait_for_pending_acks();
        }

        $this->close();
    }


    public function isAck(bool $isAck): static
    {
        $this->isAck = $isAck;

        return $this;
    }


    /**
     * @return void
     * @throws Throwable
     */
    public function close(): void
    {
        try {
            $this->channel->close();
            $this->connection->close();
            \Log::channel("rabbit_mq")->info("mqTool关闭 {$this->uuid}");
        } catch (Throwable $e) {
            \Log::channel("rabbit_mq")->error("mqTool关闭失败 {$this->uuid}", [
                "error" => $e->getMessage(),
                "line" => $e->getLine(),
                "trace" => $e->getTrace(),
                "config" => HelperUtil::autoJsonEncode($this->config)
            ]);
            throw new $e;
        }
    }



//    /**
//     * @throws Exception|\Throwable
//     */
//    public function __destruct()
//    {
//
//    }

}
