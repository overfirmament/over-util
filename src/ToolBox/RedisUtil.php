<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Support\Facades\Redis;
use Illuminate\Redis\Connections\Connection;

class RedisUtil
{
    protected Connection $redis;


    public static function init($connection = null): RedisUtil
    {
        $redisUtil = new self();
        $connection = $connection ?? "default";
        $redisUtil->redis = Redis::connection($connection);
        return $redisUtil;
    }


    /**
     * @param  string  $key
     * @param  array  $data
     * @param  int  $ttl
     *
     * @return mixed
     */
    public function hmset(string $key, array $data, int $ttl = 0): mixed
    {
        $hash = [];
        foreach ($data as $k => $v) {
            $hash[] = $k;
            $hash[] = $v;
        }
        $res = $this->redis->hmset($key, ...$hash);

        if ($ttl) {
            $this->redis->expire($key, $ttl);
        }

        return $res;
    }


    public function action(string $action, $param)
    {
        return $this->redis->$action($param);
    }
}
