<?php

namespace App\Console\Commands;

use App\Services\Rebate\RebateQueueRecoveryService;
use Illuminate\Console\Command;

class RecoverRebateQueueCommand extends Command
{
    protected $signature = 'rebate:recover-queue {--limit=200}';

    protected $description = '补投已入库但尚未进入队列的返利任务';

    public function handle(RebateQueueRecoveryService $recovery): int
    {
        $result = $recovery->dispatch((int) $this->option('limit'));
        $this->info("返利事件 {$result['events']} 条，提现 {$result['withdrawals']} 条已检查投递。");

        return self::SUCCESS;
    }
}
