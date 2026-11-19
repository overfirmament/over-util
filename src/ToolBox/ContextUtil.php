<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\HigherOrderTapProxy;

/**
 * 该工具类仅用于上下文注入
 *
 *
 * @extends Fluent<string, mixed>
 *
 * @phpstan-consistent-constructor
 */
class ContextUtil extends Fluent
{
    /**
     * @var static|null
     */
    protected static ?ContextUtil $context = null;

    public static function getInstance(): static
    {
        return static::$context ??= new static;
    }

    /**
     * Drop the singleton so long-running workers do not leak context between
     * jobs/requests.
     */
    public static function reset(): void
    {
        static::$context = null;
    }

    /**
     * @param  array<string, mixed>|string  $key
     * @param  mixed  $value
     * @return $this
     */
    #[\Override]
    public function set($key, $value = null): static
    {
        $data = is_array($key) ? $key : [$key => $value];

        foreach ($data as $key => $value) {
            Arr::set($this->attributes, $key, $value);
        }

        return $this;
    }

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return array|\ArrayAccess|mixed
     */
    #[\Override]
    public function get($key, $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @param  string  $key
     * @return array|\ArrayAccess|HigherOrderTapProxy|mixed|null
     */
    public function remember($key, \Closure $callback): mixed
    {
        if (($value = $this->get($key)) !== null) {
            return $value;
        }

        return tap($callback(), function ($value) use ($key) {
            $this->set($key, $value);
        });
    }

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return array<array-key, mixed>
     */
    public function getArray($key, $default = null): array
    {
        return HelperUtil::array($this->get($key, $default), false);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @param  array-key|null  $k
     * @return $this|ContextUtil
     */
    public function add($key, $value, $k = null): ContextUtil|static
    {
        $results = $this->getArray($key);

        if ($k === null) {
            $results[] = $value;
        } else {
            $results[$k] = $value;
        }

        return $this->set($key, $results);
    }

    /**
     * @param  string  $key
     * @param  array<array-key, mixed>  $value
     * @return $this|ContextUtil
     */
    public function merge($key, array $value): ContextUtil|static
    {
        $results = $this->getArray($key);

        return $this->set($key, array_merge($results, $value));
    }

    /**
     * @param  array<int, string>|string  $keys
     */
    public function forget($keys): void
    {
        Arr::forget($this->attributes, $keys);
    }

    public function flush(): void
    {
        $this->attributes = [];
    }
}
