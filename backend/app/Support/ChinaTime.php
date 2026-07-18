<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class ChinaTime
{
    public const FORMAT = 'Y-m-d H:i:s';

    public static function range(string $startDate, string $endDate): ChinaDateRange
    {
        return ChinaDateRange::make($startDate, $endDate);
    }

    public static function fmt(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $time = $val instanceof DateTimeInterface
            ? CarbonImmutable::instance($val)
            : CarbonImmutable::parse((string) $val);

        return $time->setTimezone($tz)->format(self::FORMAT);
    }

    public static function fmtUtc(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        $time = $val instanceof DateTimeInterface
            ? CarbonImmutable::instance($val)
            : CarbonImmutable::parse((string) $val, 'UTC');

        return $time
            ->setTimezone(config('ledger.timezone', 'Asia/Shanghai'))
            ->format(self::FORMAT);
    }

    public static function utcText(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
