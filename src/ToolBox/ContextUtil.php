<?php

namespace Overfirmament\OverUtils\ToolBox;

use Dcat\Admin\Support\Helper;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\HigherOrderTapProxy;

/**
 * 该工具类仅用于上下文注入
 * @package App\Utils
 */
class ContextUtil extends Fluent
{
    protected static ?ContextUtil $context = null;

    /**
     * @return self
     */
    public static function getInstance(): ContextUtil
    {
        return static::$context ??= new self();
    }


    /**
     * @param $key
     * @param $value
     *
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
     * @param $key
     * @param $default
     *
     * @return array|\ArrayAccess|mixed
     */
    public function get($key, $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @param $key
     * @param  \Closure  $callback
     *
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
     * @param $key
     * @param $default
     *
     * @return array
     */
    public function getArray($key, $default = null): array
    {
        return Helper::array($this->get($key, $default), false);
    }


    /**
     * @param $key
     * @param $value
     * @param $k
     *
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
     * @param $key
     * @param  array  $value
     *
     * @return $this|ContextUtil
     */
    public function merge($key, array $value): ContextUtil|static
    {
        $results = $this->getArray($key);

        return $this->set($key, array_merge($results, $value));
    }

    /**
     * @param $keys
     *
     * @return void
     */
    public function forget($keys): void
    {
        Arr::forget($this->attributes, $keys);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->attributes = [];
    }
}