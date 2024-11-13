<?php

namespace App\Annotations;


#[\Attribute(\Attribute::TARGET_PROPERTY)]
class GenericsType
{
    public function __construct(string $class)
    {
        if (strlen($class) == 0) {
            throw new \InvalidArgumentException("泛型不能为空");
        }
    }
}