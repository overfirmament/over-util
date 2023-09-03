<?php

namespace Overfirmament\OverUtils\ToolBox;

class ObjectUtil
{
    /**
     * @param  array|null  $arr
     * @param  object  $object
     *
     * @return object
     */
    public static function parseObject(?array $arr, object $object): object
    {
        if (empty($arr)) {
            return $object;
        }

        $convertArr = [];
        foreach ($arr as $key => $value) {
            $convertArr[\Str::camel($key)] = $value;
        }

        $class = (new \ReflectionClass($object))->getProperties();

        foreach ($class as $property) {
            $property->setAccessible(true);
            // 对象中的变量或者属性必须是驼峰命名法
            $name = $property->getName();
            if (isset($convertArr[$name])) {
                $property->setValue($object, $convertArr[$name]);
            }
        }

        return $object;
    }
}