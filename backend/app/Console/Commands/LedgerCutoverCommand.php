<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerCutoverService;
use App\Support\ChinaTime;
use Illuminate\Console\Command;
use Throwable;

class LedgerCutoverCommand extends Command
{
    protected $signature = 'ledger:cutover {--at= : 中国时间，格式 YYYY-MM-DD HH:mm:ss}';

    protected $description = '首次写入并永久锁定审计账本切账时间';

    public function handle(LedgerCutoverService $cutover): int
    {
        $at = trim((string) $this->option('at'));
        if ($at === '') {
            $this->error('必须提供 --at="YYYY-MM-DD HH:mm:ss"');

            return self::FAILURE;
        }

        try {
            $utc = $cutover->setOnce($at);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $current = $cutover->get();
            if ($current) {
                $this->line('当前中国时间：'.ChinaTime::fmtUtc($current));
                $this->line('当前 UTC：'.ChinaTime::utcText($current));
            }

            return self::FAILURE;
        }

        $this->info('切账时间已锁定，后续不可修改。');
        $this->line('中国时间：'.ChinaTime::fmtUtc($utc));
        $this->line('UTC：'.ChinaTime::utcText($utc));

        return self::SUCCESS;
    }
}
