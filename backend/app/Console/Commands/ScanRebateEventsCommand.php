<?php

namespace App\Console\Commands;

use App\Services\Rebate\RebateScanService;
use Illuminate\Console\Command;

class ScanRebateEventsCommand extends Command
{
    protected $signature = 'rebate:scan {--source=} {--limit=}';

    protected $description = '扫描 Sub2API 切换后的返利事件';

    public function handle(RebateScanService $scanner): int
    {
        $source = trim((string) $this->option('source')) ?: null;
        $limit = (int) ($this->option('limit') ?: config('rebate.scan_limit', 200));
        $result = $scanner->scan($source, min(max($limit, 1), 1000));

        foreach ($result as $name => $row) {
            $this->line(sprintf(
                '%s: 读取 %d，新增 %d，重复 %d，跳过 %d，游标 %s%s',
                $name,
                $row['read_count'],
                $row['created_count'],
                $row['duplicate_count'],
                $row['skipped_count'],
                $row['cursor'] ?? '-',
                $row['has_more'] ? '，仍有后续数据' : '',
            ));
        }

        return self::SUCCESS;
    }
}
