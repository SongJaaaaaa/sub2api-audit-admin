<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ChinaTime
{
    public static function dayRange(string $date): array
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $day = CarbonImmutable::parse($date, $tz);

        return [$day->startOfDay()->utc(), $day->endOfDay()->utc()];
    }
}
