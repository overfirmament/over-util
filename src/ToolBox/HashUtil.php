<?php

namespace Overfirmament\OverUtils\ToolBox;

/**
 * 这个类是为了通过id获取分表的表名后缀
 */
class HashUtil
{
    public static function hash($key, $seed = 305441741): int|string
    {
        $m   = -4132994306676758123;
        $r   = 47;
        $len = strlen($key);

        $h     = $seed ^ (self::multi64($len, $m));
        $bytes = self::getBytes($key);

        for ($i = 0; $i <= ($len / 8) - 1; $i++) {
            $k = 0;
            for ($j = 0; $j < 8; $j++) {
                $k = ($k << 8) | $bytes[$i * 8 + 7 - $j];
            }
            $k = self::multi64($k, $m);
            $k ^= self::rShift($k, $r);
            $k = self::multi64($k, $m);
            $h ^= $k;
            $h = self::multi64($h, $m);
        }

        $data2Index = $len - $len % 8;
        switch ($len & 7) {
            case 7:
                $h ^= ($bytes[$data2Index + 6]) << 48;
            case 6:
                $h ^= ($bytes[$data2Index + 5]) << 40;
            case 5:
                $h ^= ($bytes[$data2Index + 4]) << 32;
            case 4:
                $h ^= ($bytes[$data2Index + 3]) << 24;
            case 3:
                $h ^= ($bytes[$data2Index + 2]) << 16;
            case 2:
                $h ^= ($bytes[$data2Index + 1]) << 8;
            case 1:
                $h ^= ($bytes[$data2Index + 0]);
                $h = self::multi64($h, $m);
        };

        $h ^= self::rShift($h, $r);
        $h = self::multi64($h, $m);
        $h ^= self::rShift($h, $r);

        return $h;
    }


    private static function getBytes($str): array
    {
        $len = strlen($str);
        $bytes = [];
        for($i=0;$i<$len;$i++) {
            $bytes[] =  ord($str[$i]);
        }
        return $bytes;
    }

    private static function multi64($x, $y): int
    {
        $result = 0;
        for($i = 0; $i < 64; $i++) {
            $bit = ($x >> $i) & 1;
            if($bit) {
                $result = self::add64($result, $y << $i);
            }
        }
        return $result;
    }

    private static function rShift($num, $bit) {
        if($bit <= 0) return $num;
        if($num > 0) {
            return $num>>$bit;
        } else {
            $num = $num>>1;
            $num = $num & 0x7FFFFFFFFFFFFFFF;
            return self::rShift($num, $bit - 1);
        }
    }

    private static function add64($x,$y): int
    {
        $jw = $x & $y;
        $jg = $x ^ $y;
        while($jw)
        {
            $t_a = $jg;
            $t_b = $jw << 1;
            $jw = $t_a & $t_b;
            $jg = $t_a ^ $t_b;
        }
        return $jg;
    }


    /**
     * @param $primaryKey
     *
     * @return float|int
     */
    public static function tableSuffix($primaryKey): float|int
    {
        return abs(self::hash((string) $primaryKey) % 10);
    }
}
