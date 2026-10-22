<?php

namespace Overfirmament\OverUtils\Traits;

trait EnumDescriptionTrait
{
    public static function asSelectArray(): array
    {
        $data = [];
        foreach (self::cases() as $case) {
            $data[$case->value] = $case->description();
        }

        return $data;
    }

    public static function sqlComment(string $pre = ''): string
    {
        $selectArray = self::asSelectArray();
        $commentArray = [];
        foreach ($selectArray as $k => $v) {
            $commentArray[] = "{$k}-{$v}";
        }

        return "{$pre}：".implode('|', $commentArray);
    }
}
