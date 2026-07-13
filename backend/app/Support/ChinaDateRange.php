<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ChinaDateRange
{
    private function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $timezone,
        public readonly CarbonImmutable $localStart,
        public readonly CarbonImmutable $localEndExclusive,
        public readonly CarbonImmutable $utcStart,
        public readonly CarbonImmutable $utcEndExclusive,
    ) {}

    public static function make(string $startDate, string $endDate, ?string $timezone = null): self
    {
        $tz = $timezone ?: config('ledger.timezone', 'Asia/Shanghai');
        $start = self::date($startDate, $tz);
        $end = self::date($endDate, $tz);

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('开始日期不能晚于结束日期');
        }

        $localStart = $start->startOfDay();
        $localEnd = $end->addDay()->startOfDay();

        return new self(
            $startDate,
            $endDate,
            $tz,
            $localStart,
            $localEnd,
            $localStart->utc(),
            $localEnd->utc(),
        );
    }

    public static function day(string $date, ?string $timezone = null): self
    {
        return self::make($date, $date, $timezone);
    }

    public function apiParams(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'timezone' => $this->timezone,
        ];
    }

    public function localStartText(): string
    {
        return $this->localStart->format(ChinaTime::FORMAT);
    }

    public function localEndExclusiveText(): string
    {
        return $this->localEndExclusive->format(ChinaTime::FORMAT);
    }

    public function utcStartText(): string
    {
        return ChinaTime::utcText($this->utcStart);
    }

    public function utcEndExclusiveText(): string
    {
        return ChinaTime::utcText($this->utcEndExclusive);
    }

    public function dates(): array
    {
        $dates = [];

        for ($day = $this->localStart; $day->lessThan($this->localEndExclusive); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    private static function date(string $date, string $timezone): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if (! $time || $time->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('日期格式必须为 YYYY-MM-DD');
        }

        return $time;
    }
}
