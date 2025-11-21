<?php

namespace Overfirmament\OverUtils\Traits;

trait EnumHandleTrait
{
    /**
     * @return array
     */
    public static function values(): array
    {
        $data = [];
        $allEnums = self::cases();
        // 获取每个枚举的 value 放入数组
        foreach ($allEnums as $enum) {
            $data[] = $enum->value;
        }

        return $data;
    }


    public function is(mixed $value): bool
    {
        // 如果传入的值是枚举类的值，直接比较 value；传入的是枚举类的实例，则比较实例
        if ($value instanceof \BackedEnum) {
            return $this === $value;
        }

        return $this->value === $value;
    }


    /**
     * @param $value
     *
     * @return bool
     */
    public static function isBelong($value): bool
    {
        return (bool) self::tryFrom($value);
    }
}