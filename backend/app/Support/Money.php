<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function fmt(mixed $val): string
    {
        $amount = trim((string) ($val ?? '0')) ?: '0';
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('金额格式无效');
        }

        $rounded = bcadd($amount, str_starts_with($amount, '-') ? '-0.005' : '0.005', 2);

        return $rounded === '-0.00' ? '0.00' : $rounded;
    }

    public static function add(mixed $a, mixed $b): string
    {
        return bcadd(self::fmt($a), self::fmt($b), 2);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return bcsub(self::fmt($a), self::fmt($b), 2);
    }

    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, self::fmt($value), 2);
        }

        return $total;
    }
}
