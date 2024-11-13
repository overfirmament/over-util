<?php

namespace Overfirmament\OverUtils\ToolBox;

use App\Annotations\GenericsType;
use App\Annotations\Id;
use App\Utils\HelperUtil;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Exception;
use ReflectionClass;
use ReflectionException;

class ObjectUtil
{
    /**
     * 数组转对象
     *
     * @param  array|null  $arr
     * @param  object|string  $object  $object
     *
     * @return object
     * @throws Exception
     */
    public static function parseObject(?array $arr, object|string $object): object
    {
        $object = is_string($object) ? new $object() : $object;

        if (empty($arr)) {
            return $object;
        }

        $convertArr = [];
        foreach ($arr as $key => $value) {
            $convertArr[\Str::camel($key)] = $value;
        }

        $class = (new ReflectionClass($object))->getProperties();

        foreach ($class as $property) {
            $property->setAccessible(true);
            $type = $property->getType()->getName();
            $allowNull = $property->getType()->allowsNull();
            $defaultValue = $property->getDefaultValue();
            // 对象中的变量或者属性必须是驼峰命名法
            $name = $property->getName();
            if (isset($convertArr[$name])) {
                $convertValue = self::convertValueType($type, $convertArr[$name], $defaultValue, $allowNull);
                $property->setValue($object, $convertValue);
            }
        }

        return $object;
    }

    /**
     * @template T
     * @param  array|null|string  $arr
     * @param  T|object<T>|string $object
     *
     * @return T
     * @throws Exception
     */
    public static function setObject(array|string|null $arr, object|string $object): object
    {
        $object = is_string($object) ? new $object() : $object;

        if (empty($arr)) {
            return $object;
        }

        if (is_string($arr)) {
            if ($object instanceof CarbonInterface) {
                return Carbon::parse($arr);
            }
            $arr = HelperUtil::autoJsonDecode($arr);
        }

        $convertArr = [];
        foreach ($arr as $key => $value) {
            $convertArr[\Str::camel($key)] = $value;
        }

        $class      = (new ReflectionClass($object));
        $properties = $class->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();

            // Check if the property exists in the converted array
            if (!array_key_exists($name, $convertArr)) {
                continue;
            }

            // Check if a setter exists for the property
            $setter = "set" . \Str::studly($name);
            if (!$class->hasMethod($setter)) {
                continue;
            }

            // Get the property type
            $type = $property->getType()?->getName();
            $allowNull = $property->getType()?->allowsNull();
            $defaultValue = $property->getDefaultValue();

            // Convert the value type
            $convertValue = self::convertValueType($type, $convertArr[$name], $defaultValue, $allowNull);

            // Handle generic class type if specified
            $genericType = \Arr::first($property->getAttributes(), function (\ReflectionAttribute $i) {
                return $i->getName() == GenericsType::class;
            });
            $genericClass = $genericType && $genericType->getArguments() ? $genericType->getArguments()[0] : "";

            if ($genericClass && is_array($convertValue)) {
                $convertValue = array_map(function ($item) use ($genericClass) {
                    return self::setObject($item, $genericClass);
                }, $convertValue);
            }

            // Invoke the setter method with the converted value
            $class->getMethod($setter)->invoke($object, $convertValue);
        }

        return $object;
    }


    /**
     * @param $propertyTypeName
     * @param $value
     * @param $defaultValue
     * @param  bool  $allowNull
     *
     * @return array|bool|float|int|mixed|object|string
     * @throws Exception
     */
    protected static function convertValueType($propertyTypeName, $value, $defaultValue, bool $allowNull = true): mixed
    {
        if(is_null($value)) {
            return $allowNull ? null : $defaultValue;
        }

        switch ($propertyTypeName) {
            case 'string':
                if (is_array($value)) {
                    // 数组转换为 JSON 字符串
                    $value = HelperUtil::autoJsonEncode($value);
                } elseif (is_object($value)) {
                    // 对象转换为 JSON 字符串
                    $value = HelperUtil::autoJsonEncode($value);
                } else {
                    // 其他类型转换为字符串
                    $value = (string)$value;
                }
                break;
            case 'long':
            case 'int':
                if (Carbon::canBeCreatedFromFormat($value, 'Y-m-d H:i:s')) {
                    $value = Carbon::parse($value)->getTimestampMs();
                } else {
                    $value = (int)$value;
                }
                break;
            case 'float':
                $value = (float)$value;
                break;
            case 'bool':
                $value = (bool)$value;
                break;
            case 'array':
                if (is_object($value)) {
                    if (method_exists($value, "toArray")) {
                        $value = $value->toArray();
                    } elseif(method_exists($value, "asArray")) {
                        $value = $value->asArray();
                    }
                } elseif(is_string($value)) {
                    if (\Str::startsWith($value, ["{", "["]) && \Str::endsWith($value, ["}", "]"])) {
                        $value = HelperUtil::autoJsonDecode($value);
                    } else {
                        $value = explode(",", $value);
                    }
                }else {
                    $value = (array)$value;
                }
                break;
            case 'object':
                if (!is_object($value)) {
                    // 如果不是对象，则尝试转换为对象
                    $value = (object)$value;
                }
                break;
            default:
                if (class_exists($propertyTypeName)) {
                    // 如果是自定义类，则尝试转换为自定义类
                    $value = self::setObject($value, $propertyTypeName);
                } else {
                    throw new Exception("不支持的转换类型: {$propertyTypeName}");
                }
        }

        return $value;
    }


    /**
     * @template T
     * @param  array|null|string  $arr
     * @param  T|object<T>|string $object
     *
     * @return T
     * @throws Exception
     */
    public static function parseESObject(array|string|null $item, object|string $object): object
    {
        $object = is_string($object) ? new $object() : $object;

        $arr = $item["_source"] ?? [];
        if (empty($arr)) {
            return $object;
        }

        if (is_string($arr)) {
            if ($object instanceof CarbonInterface) {
                return Carbon::parse($arr);
            }
            $arr = HelperUtil::autoJsonDecode($arr);
        }

        $convertArr = [];
        foreach ($arr as $key => $value) {
            $convertArr[\Str::camel($key)] = $value;
        }

        $class      = (new ReflectionClass($object));
        $properties = $class->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            // Get the property type
            $type = $property->getType()?->getName();
            $allowNull = $property->getType()?->allowsNull();
            $defaultValue = $property->getDefaultValue();
            $attributes = collect($property->getAttributes())->keyBy(function ($item) {
                return $item->getName();
            })->toArray();
            $setter = "set" . \Str::studly($name);

            // 主键定义优先
            if (array_key_exists(Id::class, $attributes)) {
                $class->getMethod($setter)->invoke($object, $item["_id"]);
            }

            // Check if the property exists in the converted array
            if (!array_key_exists($name, $convertArr)) {
                continue;
            }

            // Check if a setter exists for the property
            $setter = "set" . \Str::studly($name);
            if (!$class->hasMethod($setter)) {
                continue;
            }

            // Convert the value type
            $convertValue = self::convertValueType($type, $convertArr[$name], $defaultValue, $allowNull);

            // Handle generic class type if specified
            $genericType = \Arr::first($attributes, function (\ReflectionAttribute $i) {
                return $i->getName() == GenericsType::class;
            });
            $genericClass = $genericType && $genericType->getArguments() ? $genericType->getArguments()[0] : "";

            if ($genericClass && is_array($convertValue)) {
                $convertValue = array_map(function ($item) use ($genericClass) {
                    return self::setObject($item, $genericClass);
                }, $convertValue);
            }

            // Invoke the setter method with the converted value
            $class->getMethod($setter)->invoke($object, $convertValue);
        }

        return $object;
    }
}