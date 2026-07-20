<?php

namespace App\Services\Ledger;

use App\Models\SystemSetting;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LedgerCutoverService
{
    public const KEY = 'ledger_cutover_at';

    public function get(): ?CarbonImmutable
    {
        $value = SystemSetting::query()->whereKey(self::KEY)->value('value');

        return $value ? CarbonImmutable::parse((string) $value, 'UTC')->utc() : null;
    }

    public function setOnce(string $at): CarbonImmutable
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', trim($at), $tz);

        if (! $local || $local->format(ChinaTime::FORMAT) !== trim($at)) {
            throw new InvalidArgumentException('切账时间格式必须为 YYYY-MM-DD HH:mm:ss');
        }

        return DB::transaction(function () use ($local): CarbonImmutable {
            $current = SystemSetting::query()->whereKey(self::KEY)->lockForUpdate()->first();
            if ($current) {
                throw new RuntimeException('切账时间已锁定：'.ChinaTime::fmtUtc($current->value));
            }

            $utc = $local->utc();
            SystemSetting::query()->create([
                'key' => self::KEY,
                'value' => ChinaTime::utcText($utc),
                'locked_at' => now(),
            ]);

            return $utc;
        });
    }

    public function ledgerLocalBounds(ChinaDateRange $range): ?array
    {
        $start = $range->localStart;
        $cutover = $this->get();

        if ($cutover) {
            $cutoverLocal = $cutover->setTimezone($range->timezone);
            if ($cutoverLocal->greaterThan($start)) {
                $start = $cutoverLocal;
            }
        }

        if ($start->greaterThanOrEqualTo($range->localEndExclusive)) {
            return null;
        }

        return [$start, $range->localEndExclusive];
    }
}
