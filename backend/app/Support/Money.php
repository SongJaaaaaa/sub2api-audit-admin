<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function fmt(mixed $val): string
    {
        return self::decimal($val, 2);
    }

    public static function decimal(mixed $val, int $scale): string
    {
        $amount = trim((string) ($val ?? '0')) ?: '0';
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('金额格式无效');
        }
        if ($scale < 0) {
            throw new InvalidArgumentException('金额精度无效');
        }

        $half = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($amount, str_starts_with($amount, '-') ? '-'.$half : $half, $scale);

        return bccomp($rounded, '0', $scale) === 0
            ? bcadd('0', '0', $scale)
            : $rounded;
    }

    public static function add(mixed $a, mixed $b, int $scale = 2): string
    {
        return bcadd(self::decimal($a, $scale), self::decimal($b, $scale), $scale);
    }

    public static function sub(mixed $a, mixed $b, int $scale = 2): string
    {
        return bcsub(self::decimal($a, $scale), self::decimal($b, $scale), $scale);
    }

    public static function sum(iterable $values, int $scale = 2): string
    {
        $total = bcadd('0', '0', $scale);
        foreach ($values as $value) {
            $total = bcadd($total, self::decimal($value, $scale), $scale);
        }

        return $total;
    }

    public static function abs(mixed $val, int $scale = 2): string
    {
        return ltrim(self::decimal($val, $scale), '+-');
    }

    public static function compare(mixed $a, mixed $b, int $scale = 2): int
    {
        return bccomp(self::decimal($a, $scale), self::decimal($b, $scale), $scale);
    }
}
