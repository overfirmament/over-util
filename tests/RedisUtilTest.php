<?php

namespace Overfirmament\OverUtils\Tests;

use ArrayIterator;
use BadMethodCallException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Overfirmament\OverUtils\ToolBox\RedisUtil;
use PHPUnit\Framework\Attributes\Test;

class RedisUtilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RedisUtil::clearInstance();
    }

    protected function tearDown(): void
    {
        RedisUtil::clearInstance();
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_scans_every_page_through_predis(): void
    {
        $client = new FakePredisClient([
            [12, ['user:1']],
            [0, ['user:2']],
        ]);

        $this->bindConnection(new PredisConnection($client));

        $this->assertSame(['user:1', 'user:2'], RedisUtil::init()->patternKeys('user:*'));
        $this->assertSame([
            ['cursor' => 0, 'options' => ['match' => 'user:*', 'count' => 1000]],
            ['cursor' => 12, 'options' => ['match' => 'user:*', 'count' => 1000]],
        ], $client->calls);
    }

    #[Test]
    public function it_merges_and_deduplicates_multiple_patterns(): void
    {
        $client = new FakePredisClient([
            [0, ['a:1', 'shared']],
            [0, ['b:1', 'shared']],
        ]);

        $this->bindConnection(new PredisConnection($client));

        $this->assertSame(
            ['a:1', 'shared', 'b:1'],
            RedisUtil::init()->patternKeys('a:*', 'b:*')
        );
    }

    #[Test]
    public function it_returns_an_empty_array_when_predis_finds_no_keys(): void
    {
        $client = new FakePredisClient([[0, []]]);

        $this->bindConnection(new PredisConnection($client));

        $this->assertSame([], RedisUtil::init()->scanAll('missing:*'));
    }

    #[Test]
    public function it_returns_false_when_predis_returns_an_unexpected_value(): void
    {
        $client = new FakePredisClient([null]);

        $this->bindConnection(new PredisConnection($client));

        $this->assertFalse(RedisUtil::init()->scan());
    }

    #[Test]
    public function it_scans_every_node_of_a_predis_cluster(): void
    {
        $first = new FakePredisClient([
            [5, ['a:1']],
            [0, ['a:2']],
        ]);
        $second = new FakePredisClient([
            [0, ['b:1']],
        ]);

        $this->bindConnection(new PredisClusterConnection(new ArrayIterator([
            'first' => $first,
            'second' => $second,
        ])));

        $this->assertSame(['a:1', 'a:2', 'b:1'], RedisUtil::init()->patternKeys('*'));
        $this->assertCount(2, $first->calls);
        $this->assertCount(1, $second->calls);
    }

    #[Test]
    public function a_predis_cluster_has_no_second_global_page(): void
    {
        $client = new FakePredisClient([[0, ['a:1']]]);

        $this->bindConnection(new PredisClusterConnection(new ArrayIterator([$client])));

        $this->assertFalse(RedisUtil::init()->scan(5, '*'));
        $this->assertSame([], $client->calls);
    }

    private function bindConnection(Connection $connection): void
    {
        Redis::shouldReceive('connection')->andReturn($connection);
    }
}

/**
 * Mimics Predis\Client: scan() is dispatched through __call(), so
 * method_exists($client, 'scan') is false.
 */
class FakePredisClient
{
    /**
     * @var array<int, array{cursor: int, options: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * @param  array<int, array{0: int, 1: array<int, string>}|null>  $responses
     */
    public function __construct(private array $responses) {}

    public function __call(string $method, array $arguments): mixed
    {
        if ($method !== 'scan') {
            throw new BadMethodCallException("Unexpected Redis command: {$method}");
        }

        $this->calls[] = [
            'cursor' => (int) $arguments[0],
            'options' => (array) $arguments[1],
        ];

        return array_shift($this->responses);
    }
}
