<?php

namespace App\Services\Rebate;

use InvalidArgumentException;

final class RebateMoney
{
    public static function normalize(int|string $value, int $scale = 2): string
    {
        $text = trim((string) $value);
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $text)) {
            throw new InvalidArgumentException('金额格式无效');
        }

        $parts = explode('.', ltrim($text, '+'), 2);
        $extra = substr($parts[1] ?? '', $scale);
        if ($extra !== '' && trim($extra, '0') !== '') {
            throw new InvalidArgumentException("金额最多保留 {$scale} 位小数");
        }

        $result = bcadd($text, '0', $scale);

        return bccomp($result, '0', $scale) === 0
            ? bcadd('0', '0', $scale)
            : $result;
    }

    public static function positive(int|string $value): string
    {
        $amount = self::normalize($value);
        if (bccomp($amount, '0', 2) !== 1) {
            throw new InvalidArgumentException('金额必须大于 0');
        }

        return $amount;
    }

    public static function add(int|string $left, int|string $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function sub(int|string $left, int|string $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function compare(int|string $left, int|string $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }

    public static function multiply(int|string $amount, int|string $rate): string
    {
        return bcmul(self::normalize($amount), self::normalize($rate, 4), 2);
    }

    public static function times(int|string $amount, int $times): string
    {
        if ($times < 0) {
            throw new InvalidArgumentException('次数不能小于 0');
        }

        return bcmul(self::normalize($amount), (string) $times, 2);
    }

    public static function reached(int|string $total, int|string $step): int
    {
        $stepAmount = self::positive($step);

        return (int) bcdiv(self::normalize($total), $stepAmount, 0);
    }
}
