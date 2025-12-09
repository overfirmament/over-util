<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

class HelperUtil
{
    /**
     * 文本转成utf-8,论坛数据库类型是gbk 渲染到前端会乱码
     *
     * @param  string|null  $string  gbk-string
     *
     * @return string
     */
    public static function convertGbk(?string $string): string
    {
        return iconv('GBK', 'UTF-8//IGNORE', $string);
    }

    /**
     * iconv在转换字符”-”到gb2312时会出错，无法转换成功，无法输出。 而mb_convert_encoding没有这个bug
     *
     * @param  string|null  $string  utf8-string
     *
     * @return string
     */

    public static function convertUtf8(?string $string): string
    {
        return mb_convert_encoding($string, 'GBK', 'UTF-8');
    }

    /**
     * @param $str
     * @param  string  $in_charset
     * @param  string  $out_charset
     * @deprecated
     *
     * @return array|false|mixed|string|string[]|null
     */
    public static function array_iconv($str, string $in_charset = "gbk", string $out_charset = "utf-8"): mixed
    {
        if (is_array($str)) {
            foreach ($str as $k => $v) {
                $str[$k] = self::array_iconv($v);
            }
            return $str;
        } else {
            if (is_string($str)) {
                return mb_convert_encoding($str, $out_charset, $in_charset);
            } else {
                return $str;
            }
        }
    }

    public static function arrayIconv($str, $in_charset = "gbk", $out_charset = "utf-8")
    {
        return static::array_iconv($str, $in_charset, $out_charset);
    }

    public static function isJson($string): bool
    {
        if (is_string($string)) {
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        }

        return false;
    }


    /**
     * 数据中含有中文和"\" 用这个会更友好
     *
     * @param  array  $data
     *
     * @return string
     */
    public static function autoJsonEncode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }


    public static function autoJsonDecode(?string $json, bool $forceArray = true): array
    {
        if (!$json && $forceArray) {
            return [];
        }

        return (array) json_decode($json, $forceArray);
    }


    /**
     * @param $mobile
     *
     * @return string
     */
    public static function mobileHide($mobile): string
    {
        return substr_replace($mobile, '****', 3, 4);
    }


    /**
     * @param  int  $money
     * @param  int  $decimals
     *
     * @return string
     */
    public static function formatMoney(int $money, int $decimals = 2): string
    {
        return number_format($money / 100, $decimals, '.', '');
    }


    /**
     * @return array|string|string[]
     */
    public static function generateRequestId(): array|string
    {
        return self::uniqeStr();
    }


    /**
     * @return array|string|string[]
     */
    public static function uniqeStr(string $slug = ""): array|string
    {
        $uuid = \Str::uuid()->toString();
        return str_replace("-", $slug, $uuid);
    }


    /**
     * @param $page
     * @param $pageSize
     * @param  int  $max_page
     * @param  int  $maxPageSize
     *
     * @return array
     */
    public static function getPageInfo($page, $pageSize, int $max_page = 500, int $maxPageSize = 50): array
    {
        //设置初始页和最大页数
        $page = intval($page);
        $page = $page <= 0 ? 1 : $page;
        $page = min($page, $max_page); //最大显示页数

        //设置每页请求数和最大数
        $pageSize = intval($pageSize); //每页请求条数
        $pageSize = $pageSize > 0 && $pageSize <= $maxPageSize ? $pageSize : $maxPageSize;

        //分页设置
        $startNum = ($page - 1) * $pageSize;

        return [$page, $pageSize, $startNum];
    }


    /**
     * @param $value
     *
     * @return float|int|mixed|string
     */
    public static function redisGzinflate($value): mixed
    {
        if (!$value || is_numeric($value) || !is_string($value)) {
            return $value;
        }
        $value_un = unserialize($value);
        if (is_string($value_un) && preg_match('/^WI.*/', $value_un)) {
            //@file_put_contents('/var/log/bbs/test.log', "0_$key".'_'. strlen($value) ."\r\n", FILE_APPEND);
            $value = unserialize(gzinflate(ltrim($value_un, 'WI')));
        }
        return $value;
    }


    /**
     * 主要用于discuzx项目的redis数据存入前加密
     * \memory_driver_redis::redis_gzinflate
     *
     * @param $value
     *
     * @return string
     */
    public static function redisGzdeflate($value): string
    {
        $serialize = serialize($value);

        if(strlen($serialize) > 20000){
            //@file_put_contents('/var/log/bbs/test.log', "1_$key".'_'. strlen($value) ."\r\n", FILE_APPEND);
            $serialize = 'WI' . gzdeflate($serialize, 9);
            $serialize = serialize($serialize);
        }

        return $serialize;
    }

    /**
     * @param  string  $str
     *
     * @return string
     */
    public static function delHtml(string $str): string
    {
        $str = trim($str); //清除字符串两边的空格
        $str = preg_replace("/<table.*?>/", "", $str); //使用正则表达式匹配需要替换的内容，如空格和换行，并将替换为空。
        $str = preg_replace("/<\/table.*?>/", "", $str);
        $str = preg_replace("/<tr.*?>/", "", $str);
        $str = preg_replace("/<\/tr.*?>/", "", $str);
        $str = preg_replace("/<td.*?>/", "", $str);
        $str = preg_replace("/<\/td.*?>/", "", $str);  //匹配html中的空格
        $str = preg_replace("/<tbody.*?>/", "", $str);
        $str = preg_replace("/<\/tbody.*?>/", "", $str);
        $str = preg_replace("/<p><iframe/", "<p>\n<iframe", $str);

        return trim($str); //返回字符串
    }


    /**
     * 将数组或 collection 中的字符串数字转成 int或float
     *
     * @param  array|Collection  $data
     *
     * @return Collection|array
     */
    public static function arrayString2Number(array|Collection $data): array|Collection
    {
        if ($isArray = is_array($data)) {
            $data = collect($data);
        }

        $it = $data->map(function (array|Collection|string $item) {
            if (is_array($item) || $item instanceof Collection) {
                return self::arrayString2Number($item);
            } elseif (preg_match("/^-?\d+\.\d+?$/", $item)) {
                return floatval($item);
            } elseif (preg_match("/^-?\d+$/", $item)) {
                return intval($item);
            } else {
                return $item;
            }
        });

        if ($isArray) {
            return $it->toArray();
        } else {
            return $it;
        }
    }


    /**
     * @param  \DOMElement|\DOMText  $element
     * @param  bool  $needParentNode
     *
     * @return string
     */
    public static function parseDomElement(\DOMElement|\DOMText $element, bool $needParentNode = true): string
    {
        $html = "";
        $append = "";

        if ($needParentNode) {
            if ($element->nodeType == XML_ELEMENT_NODE){
                $html .= self::getOpenTag($element);
                $append = self::getCloseTag($element) . $append;
            } else {
                $html .= self::getTextContent($element);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                $html .= self::parseDomElement($child);
            } else {
                $html .= self::getTextContent($child);
            }
        }

        return $html . $append;
    }

    /**
     * @param  \DOMElement  $element
     *
     * @return string
     */
    private static function getOpenTag(\DOMElement $element): string
    {
        $tagName = $element->tagName;
        $attributes = $element->attributes;
        $tagAttributes = [];
        foreach ($attributes as $item) {
            $tagAttributes[] = "{$item->name}='{$item->value}'";
        }
        $attributeStr = implode(" ", $tagAttributes);

        return "<{$tagName} {$attributeStr}>";
    }

    /**
     * @param  \DOMElement  $element
     *
     * @return string
     */
    private static function getCloseTag(\DOMElement $element): string
    {
        return "</{$element->tagName}>";
    }

    /**
     * @param  \DOMText  $text
     *
     * @return string
     */
    private static function getTextContent(\DOMText $text): string
    {
        return $text->wholeText;
    }


    /**
     * 返回数据仓库文件夹路径
     *
     * @param  string  $path
     *
     * @return string
     */
    public static function repPath(string $path = ""): string
    {
        return $path ? "App\\Repositories\\{$path}\\" :  "App\\Repositories\\";
    }


    /**
     * 数组内的key转为驼峰
     *
     * @param  array  $data
     *
     * @return array
     */
    public static function camelCase(array $data): array
    {
        $array = [];
        foreach ($data as $k => $item) {
            if (is_array($item)) {
                $array[\Str::camel($k)] = self::camelCase($item);
            } else {
                $array[\Str::camel($k)] = $item;
            }
        }

        return $array;
    }


    /**
     * @param  string  $url
     *
     * @return array|false|int|string|null
     */
    public static function urlPath(string $url): bool|array|int|string|null
    {
        if (!\Str::startsWith($url, ["http", "https"])) {
            return $url;
        }

        return parse_url($url, PHP_URL_PATH);
    }


    /**
     * 域名和路径组装成url地址
     *
     * @param  string  $domain
     * @param  string  $path
     *
     * @return string
     */
    public static function spliceUrl(string $domain, string $path): string
    {
        return trim($domain, "/") . "/" . trim($path, "/");
    }


    /**
     * 把给定的值转化为数组.
     *
     * @param $value
     * @param  bool  $filter
     * @return array
     */
    public static function array($value, bool $filter = true): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if ($value instanceof \Closure) {
            $value = $value();
        }

        if (is_array($value)) {
        } elseif ($value instanceof Jsonable) {
            $value = json_decode($value->toJson(), true);
        } elseif ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif (is_string($value)) {
            $array = null;

            try {
                $array = json_decode($value, true);
            } catch (\Throwable $e) {
            }

            $value = is_array($array) ? $array : explode(',', $value);
        } else {
            $value = (array) $value;
        }

        return $filter ? array_filter($value, function ($v) {
            return $v !== '' && $v !== null;
        }) : $value;
    }


    public static function convertEmptyArrayToObject($data)
    {
        if (is_array($data)) {
            // 如果是空数组，转换成空对象
            if (empty($data)) {
                return new \stdClass();
            }

            // 递归处理数组中的每个元素
            foreach ($data as $key => $value) {
                $data[$key] = self::convertEmptyArrayToObject($value);
            }
        }
        return $data;
    }


    /**
     * 获取客户端ip
     *
     * @return string|null
     */
    public static function getClientIp(): ?string
    {
        $validator = fn($ip) => filter_var($ip, FILTER_VALIDATE_IP);

        return once(function () use ($validator) {
            $candidates = array_filter([
                request()->header('X-Forwarded-For'),
                request()->header('X-Real-IP'),
                request()->header('CLIENT_IP'),
                request()->header('CF-Connecting-IP'),
                request()->ip(),
            ], fn($v) => $validator($v));

            return collect($candidates)
                ->flatMap(fn($v) => explode(',', $v))
                ->map(fn($v) => trim($v))
                ->first($validator, request()->ip());
        });
    }


    /**
     * 内容截取
     *
     * @param $string
     * @param $length
     * @param  string  $dot
     * @param  string  $charset
     *
     * @return string
     */
    public static function cutstr($string, $length, string $dot = ' ...', string $charset = 'utf-8'): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        $pre    = chr(1);
        $end    = chr(1);
        $string = str_replace(['&amp;', '&quot;', '&lt;', '&gt;'],
            [$pre.'&'.$end, $pre.'"'.$end, $pre.'<'.$end, $pre.'>'.$end], $string);

        $strcut = '';
        if (strtolower($charset) == 'utf-8') {

            $n = $tn = $noc = 0;
            while ($n < strlen($string)) {

                $t = ord($string[$n]);
                if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                    $tn = 1;
                    $n++;
                    $noc++;
                } elseif (194 <= $t && $t <= 223) {
                    $tn  = 2;
                    $n   += 2;
                    $noc += 2;
                } elseif (224 <= $t && $t <= 239) {
                    $tn  = 3;
                    $n   += 3;
                    $noc += 2;
                } elseif (240 <= $t && $t <= 247) {
                    $tn  = 4;
                    $n   += 4;
                    $noc += 2;
                } elseif (248 <= $t && $t <= 251) {
                    $tn  = 5;
                    $n   += 5;
                    $noc += 2;
                } elseif ($t == 252 || $t == 253) {
                    $tn  = 6;
                    $n   += 6;
                    $noc += 2;
                } else {
                    $n++;
                }

                if ($noc >= $length) {
                    break;
                }

            }
            if ($noc > $length) {
                $n -= $tn;
            }

            $strcut = substr($string, 0, $n);

        } else {
            $_length = $length - 1;
            for ($i = 0; $i < $length; $i++) {
                if (ord($string[$i]) <= 127) {
                    $strcut .= $string[$i];
                } else {
                    if ($i < $_length) {
                        $strcut .= $string[$i].$string[++$i];
                    }
                }
            }
        }

        $strcut = str_replace([$pre.'&'.$end, $pre.'"'.$end, $pre.'<'.$end, $pre.'>'.$end],
            ['&amp;', '&quot;', '&lt;', '&gt;'], $strcut);

        $pos = strrpos($strcut, chr(1));
        if ($pos !== false) {
            $strcut = substr($strcut, 0, $pos);
        }

        return $strcut.$dot;
    }


    /**
     * @param $datestr
     *
     * @return false|int
     */
    public static function human2Unix($datestr): false|int
    {
        if ($datestr === '') {
            return false;
        }

        $datestr = preg_replace('/\040+/', ' ', trim($datestr));

        if (!preg_match('/^(\d{2}|\d{4})\-[0-9]{1,2}\-[0-9]{1,2}\s[0-9]{1,2}:[0-9]{1,2}(?::[0-9]{1,2})?(?:\s[AP]M)?$/i',
            $datestr)) {
            return false;
        }

        sscanf($datestr, '%d-%d-%d %s %s', $year, $month, $day, $time, $ampm);
        sscanf($time, '%d:%d:%d', $hour, $min, $sec);
        isset($sec) or $sec = 0;

        if (isset($ampm)) {
            $ampm = strtolower($ampm);

            if ($ampm[0] === 'p' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm[0] === 'a' && $hour === 12) {
                $hour = 0;
            }
        }
        return mktime($hour, $min, $sec, $month, $day, $year);
    }


    public static function unserialize(?string $string): mixed
    {
        if (empty($string)) {
            return null;
        }

        return self::autoUnserialize($string);
    }


    /**
     * @param $string
     *
     * @return mixed
     */
    public static function autoUnserialize($string): mixed
    {
        if (($ret = unserialize($string)) === false) {
            $ret = unserialize(stripslashes($string));
        }
        return $ret;
    }

    /**
     * 判断当前用户名是否可以进行修改
     *
     * @param $username
     *
     * @return bool
     */
    public static function originNameCheck($username): bool
    {
        $pattern = '/^kashen\d{8,}$/';
        $return  = false;
        if (preg_match($pattern, $username)) {
            $return = true;
        }

        return $return;
    }


    /**
     * 判断是否是序列化数据
     *
     * @param $data
     *
     * @return bool
     */
    public static function isSerialize($data): bool
    {
        $data = trim($data);
        if ('N;' == $data) {
            return true;
        }
        if (!preg_match('/^([adObis]):/', $data, $badions)) {
            return false;
        }
        switch ($badions[1]) {
            case 'a' :
            case 'O' :
            case 's' :
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
                    return true;
                }
                break;
            case 'b' :
            case 'i' :
            case 'd' :
                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
                    return true;
                }
                break;
        }
        return false;
    }
}
