<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\HigherOrderTapProxy;

/**
 * 该工具类仅用于上下文注入
 *
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
     * @return $this
     */
    public function set($key, $value = null): static
    {
        $data = is_array($key) ? $key : [$key => $value];

        foreach ($data as $key => $value) {
            Arr::set($this->attributes, $key, $value);
        }

        return $this;
    }

    /**
     * @return array|\ArrayAccess|mixed
     */
    public function get($key, $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
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

    public function getArray($key, $default = null): array
    {
        return HelperUtil::array($this->get($key, $default), false);
    }

    /**
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
     * @return $this|ContextUtil
     */
    public function merge($key, array $value): ContextUtil|static
    {
        $results = $this->getArray($key);

        return $this->set($key, array_merge($results, $value));
    }

    public function forget($keys): void
    {
        Arr::forget($this->attributes, $keys);
    }

    public function flush(): void
    {
        $this->attributes = [];
    }
}
