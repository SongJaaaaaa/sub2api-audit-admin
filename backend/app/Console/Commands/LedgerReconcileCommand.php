<?php

namespace App\Console\Commands;

use App\Services\Reconcile\ReconcileService;
use App\Support\ChinaDateRange;
use Illuminate\Console\Command;
use Throwable;

class LedgerReconcileCommand extends Command
{
    protected $signature = 'ledger:reconcile {--date= : 中国业务日期，格式 YYYY-MM-DD；默认上一自然日}';

    protected $description = '对账本地成功调额与 Sub2API 后台余额调额事件';

    public function handle(ReconcileService $service): int
    {
        $date = trim((string) $this->option('date'));
        if ($date === '') {
            $date = now(config('ledger.timezone', 'Asia/Shanghai'))->subDay()->toDateString();
        }

        try {
            ChinaDateRange::day($date);
            $batch = $service->create(null, $date);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$date} 对账完成：{$batch->status}");
        $this->line("本地成功 {$batch->local_success_count} 笔，远端匹配 {$batch->remote_matched_count} 笔，问题 {$batch->issue_count} 条。");
        $this->line("外部调额 {$batch->external_count} 笔，审计孤儿 {$batch->audit_orphan_count} 笔。");

        return self::SUCCESS;
    }
}
