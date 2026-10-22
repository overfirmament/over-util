<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Support\Facades\Redis;

/**
 * @method keys(string $key)
 * @method get(string $key)
 * @method set(string $key, mixed $value)
 * @method int setNx(string $key, mixed $value)
 * @method bool exists(string $key)
 * @method incr(string $key)
 * @method setEx(string $key, int $ttl, mixed $value)
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
 * @method string rPop(string $key)
 * @method int lLen(string $key)
 */
class RedisUtil extends Redis
{
    protected static $instances = [];

    protected Connection $redis;

    /**
     * 初始化
     */
    public static function init(string $connection = 'default'): RedisUtil
    {
        return static::$instances[$connection] ??= tap(new self, function ($redisUtil) use ($connection) {
            $redisUtil->redis = Redis::connection($connection);
        });
    }

    /**
     * 注销指定连接
     */
    public static function clearInstance(string $connection = 'default'): void
    {
        if (isset(static::$instances[$connection])) {
            unset(static::$instances[$connection]);
        }
    }

    /**
     * 返回所有连接实例
     */
    public static function getInstances(): array
    {
        return static::$instances;
    }

    /**
     * 返回具体的key值
     */
    public function patternKeys(...$patternKeys): array
    {
        $keys = [];

        foreach ($patternKeys as $pattern) {
            $keys = array_merge($keys, $this->scanAll((string) $pattern));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Iterate all keys matching a pattern with SCAN instead of the blocking
     * KEYS command.
     *
     * @return array<int, string>
     */
    public function scanAll(string $pattern = '*', int $count = 1000): array
    {
        $cursor = 0;
        $keys = [];

        do {
            $result = $this->scan($cursor, $pattern, $count);

            if ($result === false) {
                break;
            }

            [$cursor, $batch] = $result;

            if (is_array($batch)) {
                $keys = array_merge($keys, $batch);
            }
        } while ((int) $cursor !== 0);

        return $keys;
    }

    /**
     * @return array{0: int, 1: array<int, string>}|false
     */
    public function scan(int $cursor = 0, string $pattern = '*', int $count = 1000): array|false
    {
        $client = $this->redis->client();

        if ($client instanceof \Redis) {
            $keys = $client->scan($cursor, $pattern, $count);

            if ($keys === false) {
                return false;
            }

            return [$cursor, $keys];
        }

        // Laravel's cluster connection implements scan() with an options array.
        if ($this->redis instanceof PhpRedisClusterConnection) {
            return $this->redis->scan($cursor, [
                'match' => $pattern,
                'count' => $count,
            ]);
        }

        // Predis accepts an options array. Use a dynamic call because the
        // abstract connection only exposes the phpredis mixin signature.
        if (is_object($client) && method_exists($client, 'scan')) {
            $scan = \Closure::fromCallable([$client, 'scan']);
            $result = $scan($cursor, [
                'match' => $pattern,
                'count' => $count,
            ]);

            return is_array($result) ? $result : false;
        }

        return false;
    }

    public function del($key): int
    {
        if (filled($key)) {
            return (int) $this->redis->del($key);
        }

        return 0;
    }

    public function __call($name, $arguments)
    {
        if (\Str::endsWith($name, 'WithScores')) {
            $name = \Str::before($name, 'WithScores');
            $arguments[] = ['WITHSCORES' => true];
        }

        return $this->redis->$name(...$arguments);
    }
}
