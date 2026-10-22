<?php

namespace Overfirmament\OverUtils\ToolBox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * @phpstan-consistent-constructor
 */
class AlarmUtil
{
    protected string $url = '';

    protected string $key = '';

    /**
     * @var int 报警次数阈值
     */
    protected int $alarmLimit = 0;

    /**
     * @var string 报警次数阈值key
     */
    protected string $alarmLimitKey = '';

    /**
     * @var int 报警次数阈值过期时间
     */
    protected int $alarmLimitExpire = 0;

    /**
     * @var string 连接名称
     */
    protected string $connection = 'default';

    protected string $traceId = '';

    protected string $prefix = '';

    public static function init(?string $robotName = null): static
    {
        $instance = new static;
        $robotName ??= 'default';
        $config = config("alarm.qy_wx.{$robotName}") ?: config('alarm.qy_wx.default');

        if (empty($config) || ! is_array($config)) {
            throw new \RuntimeException('请先在配置文件 [alarm.php] 中添加机器人配置');
        }

        $instance->url = (string) ($config['url'] ?? '');
        $instance->key = (string) ($config['key'] ?? '');
        $instance->connection = (string) ($config['connection'] ?? 'default');
        $instance->prefix = Str::slug((string) config('app.name'));

        return $instance;
    }

    /**
     * 有时候异常会频繁触发，为了避免报警过于频繁，可以设置报警次数阈值
     *
     *
     * @return $this
     */
    public function setLimit(int $limit, string $alarmKey, int $ttl): static
    {
        $this->alarmLimit = max(0, $limit);
        $this->alarmLimitKey = $this->prefix.':alarm:'.$alarmKey;
        $this->alarmLimitExpire = max(1, $ttl);

        return $this;
    }

    /**
     * @return $this
     */
    public function setTraceId(string $traceId): static
    {
        $this->traceId = $traceId;

        return $this;
    }

    /**
     * @return $this
     */
    public function connect(string $connect = 'default'): static
    {
        $this->connection = $connect;

        return $this;
    }

    /**
     * 判断是否达到报警次数阈值
     * true-可以通知/false-无需通知
     */
    protected function checkLimit(): bool
    {
        if (filled($this->alarmLimitKey) && filled($this->alarmLimit)) {
            $hasAlarm = (int) RedisUtil::init($this->connection)->get($this->alarmLimitKey);
            if ($hasAlarm >= $this->alarmLimit) {
                return false;
            }
        }

        return true;
    }

    protected function recordLimit(): void
    {
        if (filled($this->alarmLimitKey) && filled($this->alarmLimit)) {
            $redis = RedisUtil::init($this->connection);
            if ($redis->exists($this->alarmLimitKey)) {
                $redis->incr($this->alarmLimitKey);
            } else {
                $redis->setEx($this->alarmLimitKey, $this->alarmLimitExpire, 1);
            }
        }
    }

    /**
     * @throws GuzzleException
     */
    protected function send(array $body): ResponseInterface
    {
        if ($this->url === '' || $this->key === '') {
            throw new \RuntimeException('请先初始化机器人配置');
        }

        $separator = str_contains($this->url, '?') ? '&' : '?';
        $url = $this->url.$separator.http_build_query(['key' => $this->key]);

        $client = new Client([
            'timeout' => 5,
            'connect_timeout' => 2,
            'http_errors' => true,
        ]);

        $response = $client->post($url, [
            'json' => $body,
            'headers' => ['Accept' => 'application/json'],
        ]);

        $stream = $response->getBody();
        $contents = (string) $stream;

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $payload = json_decode($contents, true);

        if (is_array($payload) && isset($payload['errcode']) && (int) $payload['errcode'] !== 0) {
            throw new \RuntimeException(
                '企业微信报警发送失败：'.($payload['errmsg'] ?? 'unknown error')
            );
        }

        $this->recordLimit();

        return $response;
    }

    /**
     * @throws GuzzleException
     */
    public function text($title, $content, array $atMobiles = [], bool $isAtAll = false): ResponseInterface|bool
    {
        if (! $this->checkLimit()) {
            return false;
        }

        $title = (string) $title;
        $content = is_array($content)
            ? HelperUtil::autoJsonEncode($content)
            : (string) $content;

        $title .= "\n project: ".config('app.name');

        if ($this->traceId !== '') {
            $title .= "\n traceId: ".$this->traceId;
        }

        $body = [
            'msgtype' => 'text',
            'text' => [
                'content' => $title."\n".$content,
            ],
        ];

        if ($isAtAll) {
            $atMobiles = array_merge($atMobiles, ['@all']);
        }

        if ($atMobiles) {
            $body['text']['mentioned_mobile_list'] = $atMobiles;
        }

        return $this->send($body);
    }

    /**
     * @throws GuzzleException
     */
    public function markdown($title, array $content): ResponseInterface|bool
    {
        if (! $this->checkLimit()) {
            return false;
        }

        $body = [
            'msgtype' => 'markdown',
            'markdown' => [

            ],
        ];

        $word = (string) $title."\n";
        $word .= '#### project: '.config('app.name')."\n";

        if ($this->traceId !== '') {
            $word .= '#### traceId: '.$this->traceId."\n";
        }

        foreach ($content as $name => $line) {
            $value = is_array($line) ? HelperUtil::autoJsonEncode($line) : (string) $line;
            $word .= '>'.$name.':'.$value."\n";
        }

        $body['markdown']['content'] = $word;

        return $this->send($body);
    }
}
