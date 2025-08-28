<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Support\Facades\Redis;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Str;

/**
 * @method keys(string $key)
 * @method get(string $key)
 * @method set(string $key, string $value)
 * @method setNx(string $key, string $value): int
 * @method bool exists(string $key)
 * @method incr(string $key)
 * @method setEx(string $key, int $ttl, string $value)
 * @method zAdd(string $key, ...$scoreAndMember)
 * @method array zRangeByScore(string $key, int $min, int $max, array $options = [])
 * @method zRangeByScoreWithScores(string $key, int $min, int $max)
 * @method zRange(string $key, int $start, int $stop)
 * @method zRangeWithScores(string $key, int $start, int $stop)
 * @method zRemRangeByScore(string $key, $min, $max)
 * @method zRemRangeByRank(string $key, $start, $end)
 * @method zCard(string $key)
 * @method pipeline(callable $callback = null)
 * @method mGet(array $key)
 * @method mSet(...$key)
 * @method publish(string $key, string $value)
 * @method expire(string $key, int $seconds)
 * @method int hDel(string $key, ...$fields)
 * @method hGet(string $key, string $field)
 * @method hSet(string $key, string $field, string $value)
 * @method hMGet(string $key, ...$fields)
 * @method hMSet(string $key, ...$fieldAndValue)
 * @method array hGetAll(string $key)
 * @method hKeys(string $key)
 * @method hVals(string $key)
 * @method sAdd(string $key, ...$members)
 * @method array sMembers(string $key)
 * @method incrBy(string $key, int $value)
 * @method zScore(string $key, string $member)
 * @method zIncrBy(string $key, int $increment, string $member)
 * @method zRem(string $key, ...$members)
 * @method zRevRangeByScore(string $key, string $max, string $min, array $options = [])
 * @method zRevRangeByScoreWithScores(string $key, string $max, string $min)
 * @method zRevRange(string $key, int $start, int $end, array $options = [])
 * @method zCount(string $key, string $min, string $max)
 * @method bool sisMember(string $key, string $member)
 * @method int hIncrBy(string $key, string $field, int $increment)
 * @method int hSetNx(string $key, string $field, mixed $value)
 * @method int hExists(string $key, string $field)
 * @method int lPush(string $key, mixed ...$values)
 * @method int decrBy(string $key, int $value = 1)
 * @method int decr(string $key)
 */
class RedisUtil extends Redis
{
    protected static $instances = [];

    protected Connection $redis;



    /**
     * 初始化
     *
     * @param  string  $connection
     *
     * @return RedisUtil
     */
    public static function init(string $connection = 'default'): RedisUtil
    {
        return static::$instances[$connection] ??= tap(new self(), function ($redisUtil) use ($connection) {
            $redisUtil->redis = Redis::connection($connection);
        });
    }



    /**
     * 返回具体的key值
     *
     * @param  $patternKeys
     *
     * @return array
     */
    public function patternKeys(...$patternKeys): array
    {
        $keys = $this->redis->pipeline(function ($pipe) use ($patternKeys) {
            foreach ($patternKeys as $key) {
                 $pipe->keys($key);
            }
        });

        return \Arr::collapse($keys);
    }


    /**
     * @param $key
     *
     * @return int
     */
    public function del($key): int
    {
        if (filled($key)) {
            return $this->redis->del($key);
        } else {
            return 0;
        }
    }


    public function __call($name, $arguments)
    {
        if (\Str::endsWith($name, "WithScores")) {
            $name = \Str::before($name, "WithScores");
            $arguments[] = ['WITHSCORES' => true];
        }
        return $this->redis->$name(...$arguments);
    }
}
