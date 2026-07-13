<?php

namespace App\Console\Commands;

use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerSourceLinkService;
use Illuminate\Console\Command;

class LedgerLinkSourcesCommand extends Command
{
    protected $signature = 'ledger:link-sources {--limit=0 : 本次最多处理多少条，0 表示全部}';

    protected $description = '按用户 ID 和完整幂等键回填 Sub2API 后台调额事件 ID';

    public function handle(LedgerSourceLinkService $linker): int
    {
        $limit = max((int) $this->option('limit'), 0);
        $query = LedgerAdjustment::query()
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->whereNull('sub2api_source_id')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $linked = 0;

        foreach ($rows as $adj) {
            if ($linker->link($adj)) {
                $linked++;
            }
        }

        $this->info("处理 {$rows->count()} 条，成功关联 {$linked} 条，仍未关联 ".($rows->count() - $linked).' 条。');

        return self::SUCCESS;
    }
}
