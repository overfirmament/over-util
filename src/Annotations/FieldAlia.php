<?php

namespace Overfirmament\OverUtils\Annotations;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FieldAlia
{
    public function __construct(string $alia)
    {
        if (strlen($alia) == 0) {
            throw new \InvalidArgumentException("属性别名不能为空");
        }
    }
}