<?php

namespace Overfirmament\OverUtils\ToolBox;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

class HelperUtil
{
    /**
     * 文本转成utf-8,论坛数据库类型是gbk 渲染到前端会乱码
     *
     * @param  string|null  $string  gbk-string
     */
    public static function convertGbk(?string $string): string
    {
        $converted = @iconv('GBK', 'UTF-8//IGNORE', (string) $string);

        return $converted === false ? '' : $converted;
    }

    /**
     * iconv在转换字符”-”到gb2312时会出错，无法转换成功，无法输出。 而mb_convert_encoding没有这个bug
     *
     * @param  string|null  $string  utf8-string
     */
    public static function convertUtf8(?string $string): string
    {
        return (string) mb_convert_encoding((string) $string, 'GBK', 'UTF-8');
    }

    /**
     * @deprecated
     *
     * @return array|false|mixed|string|string[]|null
     */
    public static function arrayIconv($str, string $inCharset = 'gbk', string $outCharset = 'utf-8'): mixed
    {
        if (is_array($str)) {
            foreach ($str as $k => $v) {
                $str[$k] = self::arrayIconv($v, $inCharset, $outCharset);
            }

            return $str;
        } else {
            if (is_string($str)) {
                return mb_convert_encoding($str, $outCharset, $inCharset);
            } else {
                return $str;
            }
        }
    }

    public static function isJson($string): bool
    {
        if (is_string($string)) {
            json_decode($string);

            return json_last_error() == JSON_ERROR_NONE;
        }

        return false;
    }

    /**
     * 数据中含有中文和"\" 用这个会更友好
     */
    public static function autoJsonEncode(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException('JSON 编码失败：'.$e->getMessage(), 0, $e);
        }
    }

    public static function autoJsonDecode(?string $json, bool $forceArray = true): array
    {
        if (! $json && $forceArray) {
            return [];
        }

        return (array) json_decode($json, $forceArray);
    }

    public static function mobileHide($mobile): string
    {
        return substr_replace($mobile, '****', 3, 4);
    }

    public static function formatMoney(int $money, int $decimals = 2): string
    {
        return number_format($money / 100, $decimals, '.', '');
    }

    public static function generateRequestId(): string
    {
        return self::uniqeStr();
    }

    public static function traceId(): string
    {
        return self::uniqeStr();
    }

    public static function uniqeStr(string $slug = ''): string
    {
        $uuid = \Str::uuid()->toString();

        return str_replace('-', $slug, $uuid);
    }

    public static function getPageInfo($page, $pageSize, int $max_page = 500, int $maxPageSize = 50): array
    {
        // 设置初始页和最大页数
        $page = intval($page);
        $page = $page <= 0 ? 1 : $page;
        $page = min($page, $max_page); // 最大显示页数

        // 设置每页请求数和最大数
        $pageSize = intval($pageSize); // 每页请求条数
        $pageSize = $pageSize > 0 && $pageSize <= $maxPageSize ? $pageSize : $maxPageSize;

        // 分页设置
        $startNum = ($page - 1) * $pageSize;

        return [$page, $pageSize, $startNum];
    }

    /**
     * @return float|int|mixed|string
     */
    public static function redisGzinflate($value, bool $allowClasses = false): mixed
    {
        // 1. 过滤无效输入
        if (! $value || is_numeric($value) || ! is_string($value)) {
            return $value;
        }
        // 2. 安全反序列化，默认禁止实例化任意类
        $value_un = @unserialize($value, ['allowed_classes' => $allowClasses]);
        if ($value_un === false) {
            return $value; // 或抛出异常取决于上下文
        }
        // 3. 精确处理 "WI" 前缀：使用 substr 而非 ltrim
        if (is_string($value_un) && str_starts_with($value_un, 'WI')) {
            $compressedData = substr($value_un, 2); // 移除 "WI" 前缀
            $inflatedData = @gzinflate($compressedData);
            if ($inflatedData === false) {
                // gzinflate 失败处理
                return $value_un; // 返回未压缩的字符串或原始值
            }
            // 4. 再次反序列化，并验证
            $result = @unserialize($inflatedData, ['allowed_classes' => $allowClasses]);
            if ($result === false) {
                return $inflatedData; // 返回解压后的字符串
            }

            return $result;
        }

        return $value_un; // 反序列化后值（可能对象、数组等）
    }

    /**
     * 主要用于discuzx项目的redis数据存入前加密
     * \memory_driver_redis::redis_gzinflate
     */
    public static function redisGzdeflate($value): string
    {
        $serialize = serialize($value);

        if (strlen($serialize) > 20000) {
            // @file_put_contents('/var/log/bbs/test.log', "1_$key".'_'. strlen($value) ."\r\n", FILE_APPEND);
            $serialize = 'WI'.gzdeflate($serialize, 9);
            $serialize = serialize($serialize);
        }

        return $serialize;
    }

    public static function delHtml(string $str): string
    {
        $str = trim($str); // 清除字符串两边的空格
        $str = preg_replace('/<table.*?>/s', '', $str); // 使用正则表达式匹配需要替换的内容，如空格和换行，并将替换为空。
        $str = preg_replace("/<\/table.*?>/s", '', $str);
        $str = preg_replace('/<tr.*?>/s', '', $str);
        $str = preg_replace("/<\/tr.*?>/s", '', $str);
        $str = preg_replace('/<td.*?>/s', '', $str);
        $str = preg_replace("/<\/td.*?>/s", '', $str);  // 匹配html中的空格
        $str = preg_replace('/<tbody.*?>/s', '', $str);
        $str = preg_replace("/<\/tbody.*?>/s", '', $str);
        $str = preg_replace('/<p><iframe/', "<p>\n<iframe", $str);

        return trim($str); // 返回字符串
    }

    /**
     * 将数组或 collection 中的字符串数字转成 int或float
     */
    public static function arrayString2Number(array|Collection $data): array|Collection
    {
        if ($isArray = is_array($data)) {
            $data = collect($data);
        }

        $it = $data->map(function ($item) {
            if (is_array($item) || $item instanceof Collection) {
                return self::arrayString2Number($item);
            }

            if (is_string($item) && preg_match('/^-?\d+\.\d+$/', $item)) {
                return (float) $item;
            }

            if (is_string($item) && preg_match('/^-?\d+$/', $item)) {
                return (int) $item;
            }

            return $item;
        });

        if ($isArray) {
            return $it->toArray();
        } else {
            return $it;
        }
    }

    public static function parseDomElement(\DOMNode $element, bool $needParentNode = true): string
    {
        $html = '';
        $append = '';

        if ($needParentNode) {
            if ($element instanceof \DOMElement && $element->nodeType == XML_ELEMENT_NODE) {
                $html .= self::getOpenTag($element);
                $append = self::getCloseTag($element).$append;
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

        return $html.$append;
    }

    private static function getOpenTag(\DOMElement $element): string
    {
        $tagName = $element->tagName;
        $attributes = $element->attributes;
        $tagAttributes = [];

        foreach ($attributes as $item) {
            $value = htmlspecialchars($item->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $tagAttributes[] = "{$item->name}='{$value}'";
        }

        $attributeStr = $tagAttributes === [] ? '' : ' '.implode(' ', $tagAttributes);

        return "<{$tagName}{$attributeStr}>";
    }

    private static function getCloseTag(\DOMElement $element): string
    {
        $voidElements = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr',
        ];

        return in_array(strtolower($element->tagName), $voidElements, true)
            ? ''
            : "</{$element->tagName}>";
    }

    private static function getTextContent(\DOMNode $node): string
    {
        return $node instanceof \DOMText ? $node->wholeText : (string) $node->nodeValue;
    }

    /**
     * 返回数据仓库文件夹路径
     */
    public static function repPath(string $path = ''): string
    {
        return $path ? "App\\Repositories\\{$path}\\" : 'App\\Repositories\\';
    }

    /**
     * 数组内的key转为驼峰
     */
    public static function camelCase(array $data): array
    {
        $array = [];
        foreach ($data as $k => $item) {
            if (is_array($item)) {
                $array[\Str::camel((string) $k)] = self::camelCase($item);
            } else {
                $array[\Str::camel((string) $k)] = $item;
            }
        }

        return $array;
    }

    /**
     * @return array|false|int|string|null
     */
    public static function urlPath(string $url): bool|array|int|string|null
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $url;
        }

        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * 域名和路径组装成url地址
     */
    public static function spliceUrl(string $domain, string $path): string
    {
        return trim($domain, '/').'/'.trim($path, '/');
    }

    /**
     * 把给定的值转化为数组.
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
            $array = json_decode($value, true);

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
                return new \stdClass;
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
     */
    public static function getClientIp(): ?string
    {
        $validator = fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP);

        return once(function () use ($validator) {
            $candidates = [];

            foreach ([
                request()->header('X-Forwarded-For'),
                request()->header('X-Real-IP'),
                request()->header('CLIENT_IP'),
                request()->header('CF-Connecting-IP'),
            ] as $header) {
                if (! is_string($header) || $header === '') {
                    continue;
                }

                foreach (explode(',', $header) as $ip) {
                    $ip = trim($ip);

                    if ($validator($ip)) {
                        $candidates[] = $ip;
                    }
                }
            }

            $requestIp = request()->ip();

            if ($validator($requestIp)) {
                $candidates[] = $requestIp;
            }

            return $candidates[0] ?? $requestIp;
        });
    }

    /**
     * 内容截取
     */
    public static function cutstr($string, $length, string $dot = ' ...', string $charset = 'utf-8'): string
    {
        $string = (string) $string;

        if (strlen($string) <= $length) {
            return $string;
        }

        $pre = chr(1);
        $end = chr(1);
        $string = str_replace(['&amp;', '&quot;', '&lt;', '&gt;'],
            [$pre.'&'.$end, $pre.'"'.$end, $pre.'<'.$end, $pre.'>'.$end], $string);

        $strcut = '';
        if (strtolower($charset) == 'utf-8') {

            $n = $tn = $noc = 0;
            while ($n < strlen($string)) {

                $t = ord($string[$n]);
                if ($t == 9 || $t == 10 || ($t >= 32 && $t <= 126)) {
                    $tn = 1;
                    $n++;
                    $noc++;
                } elseif ($t >= 194 && $t <= 223) {
                    $tn = 2;
                    $n += 2;
                    $noc += 2;
                } elseif ($t >= 224 && $t <= 239) {
                    $tn = 3;
                    $n += 3;
                    $noc += 2;
                } elseif ($t >= 240 && $t <= 247) {
                    $tn = 4;
                    $n += 4;
                    $noc += 2;
                } elseif ($t >= 248 && $t <= 251) {
                    $tn = 5;
                    $n += 5;
                    $noc += 2;
                } elseif ($t == 252 || $t == 253) {
                    $tn = 6;
                    $n += 6;
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

    public static function human2Unix($datestr): false|int
    {
        if ($datestr === '') {
            return false;
        }

        $datestr = preg_replace('/\040+/', ' ', trim($datestr));

        if (! preg_match('/^(\d{2}|\d{4})\-[0-9]{1,2}\-[0-9]{1,2}\s[0-9]{1,2}:[0-9]{1,2}(?::[0-9]{1,2})?(?:\s[AP]M)?$/i',
            $datestr)) {
            return false;
        }

        sscanf($datestr, '%d-%d-%d %s %s', $year, $month, $day, $time, $ampm);
        sscanf($time, '%d:%d:%d', $hour, $min, $sec);
        if (! isset($sec)) {
            $sec = 0;
        }

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

    public static function unserialize(?string $string, bool $allowClasses = false): mixed
    {
        if (empty($string)) {
            return null;
        }

        return self::autoUnserialize($string, $allowClasses);
    }

    public static function autoUnserialize($string, bool $allowClasses = false): mixed
    {
        if (! is_string($string) || $string === '') {
            return null;
        }

        $options = ['allowed_classes' => $allowClasses];

        if (($ret = @unserialize($string, $options)) === false) {
            $ret = @unserialize(stripslashes($string), $options);
        }

        return $ret;
    }

    /**
     * 判断当前用户名是否可以进行修改
     */
    public static function originNameCheck($username): bool
    {
        $pattern = '/^kashen\d{8,}$/';
        $return = false;
        if (preg_match($pattern, $username)) {
            $return = true;
        }

        return $return;
    }

    /**
     * 判断是否是序列化数据
     */
    public static function isSerialize($data): bool
    {
        $data = trim($data);
        if ($data == 'N;') {
            return true;
        }
        if (! preg_match('/^([adObis]):/', $data, $badions)) {
            return false;
        }
        switch ($badions[1]) {
            case 'a':
            case 'O':
            case 's':
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
                    return true;
                }
                break;
            case 'b':
            case 'i':
            case 'd':
                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * @return mixed|string
     */
    public static function getContextTraceId(): mixed
    {
        return Context::get('trace_id') ?: (Log::sharedContext()['trace_id'] ?? '');
    }

    public static function getContext(string $trace): mixed
    {
        return Context::get($trace) ?: (Log::sharedContext()[$trace] ?? '');
    }
}
