<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class ChinaTime
{
    public const FORMAT = 'Y-m-d H:i:s';

    public static function dayRange(string $date): array
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $day = CarbonImmutable::parse($date, $tz);

        return [$day->startOfDay()->utc(), $day->endOfDay()->utc()];
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

    public static function utcText(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}