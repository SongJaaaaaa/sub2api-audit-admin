<?php

namespace App\Console\Commands;

use App\Services\Rebate\RebateQueueRecoveryService;
use Illuminate\Console\Command;

class RetryFailedRebateEventsCommand extends Command
{
    protected $signature = 'rebate:retry-events {id?*} {--limit=100}';

    protected $description = '重新投递已失败的返利事件';

    public function handle(RebateQueueRecoveryService $recovery): int
    {
        $ids = array_map('intval', $this->argument('id'));
        $count = $recovery->retryFailedEvents($ids, (int) $this->option('limit'));
        $this->info("已重新投递 {$count} 条返利事件。");

        return self::SUCCESS;
    }
}
