<?php

namespace Overfirmament\OverUtils\ToolBox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class AlarmUtil
{
    protected string $url;


    /**
     * @var int 报警次数阈值
     */
    protected int $alarmLimit = 0;
    /**
     * @var string 报警次数阈值key
     */
    protected string $alarmLimitKey = "";
    /**
     * @var int 报警次数阈值过期时间
     */
    protected int $alarmLimitExpire = 0;

    /**
     * @var string 连接名称
     */
    protected string $connection = 'default';

    /**
     * 是否开启限制
     *
     * @var bool
     */
    protected bool $limited = false;

    public static function init(?string $robotName = null): static
    {
        $instance = new static();
        $config = config("alarm.qy_wx.{$robotName}") ?: config("alarm.qy_wx.default");
        if (empty($config)) {
            throw new \RuntimeException("请先在配置文件 [alarm.php] 中添加企微机器人配置");
        }
        $instance->url = $config["url"] ?? '';
        $instance->key = $config["key"] ?? '';
        $instance->alarmLimit = $config["alarmLimit"] ?? '';
        $instance->alarmLimitKey = $config["alarmLimitKey"] ?? '';
        $instance->alarmLimitExpire = $config["alarmLimitExpire"] ?? '';
        $instance->connection = $config["connection"] ?? 'default';

        return $instance;
    }


    /**
     * @param  true  $enable
     *
     * @return $this
     */
    public function limit(true $enable = true): static
    {
        $this->limited = $enable;

        return $this;
    }

    /**
     * 有时候异常会频繁触发，为了避免报警过于频繁，可以设置报警次数阈值
     *
     * @param  int  $limit
     * @param  string  $alarmKey
     * @param  int  $ttl
     *
     * @return $this
     */
    public function setLimit(int $limit, string $alarmKey, int $ttl): static
    {
        $this->alarmLimit = $limit;
        $this->alarmLimitKey = config('app.name') . ':alarm:'. $alarmKey;
        $this->alarmLimitExpire = $ttl;

        return $this;
    }


    /**
     * 判断是否达到报警次数阈值
     * true-可以通知/false-无需通知
     *
     * @return bool
     */
    protected function checkLimit(): bool
    {
        if ($this->limited && filled($this->alarmLimitKey) && filled($this->alarmLimit)) {
            $hasAlarm = RedisUtil::init($this->connection)->get($this->alarmLimitKey);
            if ($hasAlarm >= $this->alarmLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    protected function recordLimit(): void
    {
        if ($this->limited && filled($this->alarmLimitKey) && filled($this->alarmLimit)) {
            $redis = RedisUtil::init($this->connection);
            if ($redis->exists($this->alarmLimitKey)) {
                $redis->incr($this->alarmLimitKey);
            } else {
                $redis->setEx($this->alarmLimitKey, $this->alarmLimitExpire, 1);
            }
        }
    }

    /**
     * @param  array  $body
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function send(array $body): ResponseInterface
    {
        if (empty($this->url) || empty($this->key)) {
            throw new \RuntimeException("请先初始化机器人配置");
        }

        $url = $this->url . "?key=" . $this->key;
        $client = new Client();
        $send = $client->post($url, [
            "json" => $body,
        ]);

        $this->recordLimit();

        return $send;
    }

    /**
     * @param $title
     * @param $content
     * @param  array  $atMobiles
     * @param  bool  $isAtAll
     *
     * @return ResponseInterface|bool
     * @throws GuzzleException
     */
    public function text($title, $content, array $atMobiles = [], bool $isAtAll = false): ResponseInterface|bool
    {
        if (!$this->checkLimit()) {
            return false;
        }

        $body = [
            "msgtype" => "text",
            "text" => [
                "content" => $title . "\n" . $content,
            ],
        ];

        if ($isAtAll) {
            $atMobiles = array_merge($atMobiles, ["@all"]);
        }

        if ($atMobiles) {
            $body["text"]["mentioned_mobile_list"] = $atMobiles;
        }

        return $this->send($body);
    }


    /**
     * @param $title
     * @param  array  $content
     *
     * @return ResponseInterface|bool
     * @throws GuzzleException
     */
    public function markdown($title, array $content): ResponseInterface|bool
    {
        if (!$this->checkLimit()) {
            return false;
        }

        $body = [
            "msgtype" => "markdown",
            "markdown" => [

            ],
        ];

        $word = $title . "\n";
        foreach ($content as $name => $line) {
            $word .= ">" . $name . ":" . (is_array($line) ? HelperUtil::autoJsonEncode($line) : $line) . "\n";
        }

        $body["markdown"]["content"] = $word;
        return $this->send($body);
    }
}
