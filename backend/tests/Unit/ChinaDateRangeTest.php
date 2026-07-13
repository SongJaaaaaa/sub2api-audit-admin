<?php

namespace Tests\Unit;

use App\Support\ChinaDateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ChinaDateRangeTest extends TestCase
{
    public function test_china_date_range_uses_inclusive_dates_and_exclusive_end(): void
    {
        $range = ChinaDateRange::make('2026-06-30', '2026-07-01', 'Asia/Shanghai');

        $this->assertSame('2026-06-30 00:00:00', $range->localStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-02 00:00:00', $range->localEndExclusive->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-29 16:00:00', $range->utcStart->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 16:00:00', $range->utcEndExclusive->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-06-30', '2026-07-01'], $range->dates());
        $this->assertTrue(CarbonImmutable::parse('2026-07-01 23:59:59.999999', 'Asia/Shanghai')->lessThan($range->localEndExclusive));
        $this->assertFalse(CarbonImmutable::parse('2026-07-02 00:00:00', 'Asia/Shanghai')->lessThan($range->localEndExclusive));
    }

    public function test_api_params_keep_china_natural_dates(): void
    {
        $range = ChinaDateRange::make('2026-07-01', '2026-07-09', 'Asia/Shanghai');

        $this->assertSame([
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-09',
            'timezone' => 'Asia/Shanghai',
        ], $range->apiParams());
    }
}
