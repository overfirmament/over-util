<?php

namespace Overfirmament\OverUtils\Pojo;

use App\Annotations\FieldAlia;
use App\Utils\HelperUtil;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

class BasePOJO implements Arrayable
{
    const WRITE_DEFAULT_FEATURE = 0;
    const WRITE_NONSTRINGVALUE_AS_STRING = 1;
    const WRITE_NUMBERVALUE_AS_STRING = 2;
    const WRITE_BOOLEANVALUE_AS_STRING = 3;
    const WRITE_NULLSTRING_AS_EMPTY = 4;
    const REMOVE_NULLVALUE = 5;
    const KSORT = 6;
    const EMPTY_ARRAY_TO_OBJECT = 7;


    /**
     * @param  bool  $keySnake
     * @param  mixed  ...$features
     *
     * @return array
     */
    public function toArray(bool $keySnake = true, ...$features): array
    {
        $class      = (new \ReflectionClass($this));
        $properties = $class->getProperties();
        $arr        = [];

        foreach ($properties as $property) {
            $property->setAccessible(true);
            // 判断有没有别名
            $attributes = $property->getAttributes();
            $alia = \Arr::first($attributes, function (\ReflectionAttribute $i) {
                return $i->getName() == FieldAlia::class;
            });
            $name = $alia && $alia->getArguments() ? $alia->getArguments()[0] : ($keySnake ? \Str::snake($property->getName()) : $property->getName());

            try {
                $value = $property->getValue($this);

                if ($value instanceof CarbonInterface) {
                    $value = $value->toDateTimeString();
                } elseif (is_object($value) && method_exists($value, "toArray")) {

                    if ($value instanceof BasePOJO) {
                        $value = $value->toArray($keySnake, ...$features);
                    } else {
                        $value = $value->toArray();
                    }
                } elseif (is_object($value) && method_exists($value, "asArray")) {
                    assert(method_exists($value, 'asArray'), 'Value must be an object with toArray or asArray method');
                    if ($value instanceof BasePOJO) {
                        $value = $value->toArray($keySnake, ...$features);
                    } else {
                        $value = $value->asArray();
                    }
                } elseif (is_iterable($value)) {
                    foreach ($value as $k => $v) {
                        if (is_object($v)) {
                            if ($v instanceof BasePOJO) {
                                $value[$k] = $v->toArray($keySnake, ...$features);
                            } else {
                                $value[$k] = $v->asArray();
                            }
                        } else {
                            $value[$k] = $v;
                        }
                    }
                }

                $fieldPropertyType = $property->getType();
                if ($fieldPropertyType instanceof \ReflectionUnionType) {
                    $fieldTypes = \Arr::map($fieldPropertyType->getTypes(), function ($union) {
                        return $union->getName();
                    });
                } else {
                    $fieldTypes = [$fieldPropertyType->getName()];
                }

                if (in_array("bool", $fieldTypes) && $class->hasMethod("is".\Str::studly($property->getName()))) {
                    $value = $class->getMethod("is".\Str::studly($property->getName()))->invoke($this);
                }
            } catch (\Throwable $e) {
                $value = null;
            }

            // 如果是不需要null值，就直接过滤掉
            if (is_null($value) && in_array(self::REMOVE_NULLVALUE, $features)) {
                continue;
            }
            foreach ($features as $feature) {
                $value = $this->featureValue($value, $feature);
            }

            $arr[$name] = $value;
        }

        if (in_array(self::KSORT, $features)) {
            HelperUtil::recursiveKsort($arr);
        }

        return $arr;
    }


    /**
     * @param  bool  $keySnake
     * @param ...$options
     *
     * @return string
     */
    public function toJson(bool $keySnake = true, ...$options): string
    {
        $array = $this->toArray($keySnake, ...$options);
        if (in_array(self::EMPTY_ARRAY_TO_OBJECT, $options)) {
            return HelperUtil::autoJsonEncode($array, true);
        }

        return HelperUtil::autoJsonEncode($array);
    }


    protected function featureValue($value, $feature)
    {
        switch ($feature) {
            case self::WRITE_NUMBERVALUE_AS_STRING:
                if (is_int($value) || is_long($value) || is_float($value)) {
                    $value = (string) $value;
                }
                break;
            case self::WRITE_BOOLEANVALUE_AS_STRING:
                if (is_bool($value)) {
                    $value = (string) $value;
                }
                break;
            case self::WRITE_NONSTRINGVALUE_AS_STRING:
                if (is_array($value)) {
                    $value = HelperUtil::autoJsonEncode($value);
                } elseif (is_object($value)) {
                    $value = HelperUtil::autoJsonEncode($value);
                } else {
                    $value = (string) $value;
                }
                break;
            case self::WRITE_NULLSTRING_AS_EMPTY:
                if (is_null($value)) {
                    $value = "";
                }
                break;
            default:
                break;
        }

        return $value;
    }
}
