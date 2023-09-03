<?php

namespace Overfirmament\OverUtils\Traits;

trait BaseObjectTrait
{
    public function toArray(): array
    {
        $class = (new \ReflectionClass($this))->getProperties();

        $arr = [];

        foreach ($class as $property) {
            $property->setAccessible(true);
            $name = \Str::snake($property->getName());
            if (is_object($property->getValue($this)) && method_exists($property->getValue($this), "toArray")) {
                $value = $property->getValue($this)->toArray();
            } else {
                $value = $property->getValue($this);
            }
            $arr[$name] = $value;
        }

        return $arr;
    }
}