<?php

namespace Overfirmament\OverUtils\ToolBox;

use App\Exceptions\BizException;
use Illuminate\Support\Collection;
use function App\Utils\config;

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
        return iconv('GBK', 'UTF-8', $string);
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

    public static function array_iconv($str, $in_charset = "gbk", $out_charset = "utf-8")
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


    /**
     * @param  string|null  $json
     *
     * @return mixed
     */
    public static function autoJsonDecode(?string $json): mixed
    {
        return json_decode($json, true);
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
        $uuid = \Str::uuid()->toString();
        return str_replace("-", "", $uuid);
    }


    /**
     * 格式化数据库查询结果（pluck）返回给管理后台下拉框的筛选项
     *
     * @param  array|Collection  $options
     *
     * @return array
     */
    public static function formatSelectOptions(array|Collection $options): array
    {
        $result = [];
        foreach ($options as $key => $value) {
            $result[] = [
                'id'   => $key,
                'text' => $value,
            ];
        }
        return $result;
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
     * @throws BizException
     */
    public static function spliceUrl(string $domain, string $path): string
    {
        if (blank($domain)) {
            throw new BizException("The parameter [domain] is expected, but it's blank");
        }

        if (!\Str::startsWith($domain, ["http", "https"])) {
            throw new BizException("The parameter [domain] is not a legal domain name");
        }

        return trim($domain, "/") . "/" . trim($path, "/");
    }
}
