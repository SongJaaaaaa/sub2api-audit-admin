<?php

namespace App\Console\Commands;

use App\Models\Rebate\RebateScanCursor;
use App\Services\Rebate\ConfigService;
use App\Services\Rebate\RebateScanService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetRebateCutoverCommand extends Command
{
    protected $signature = 'rebate:cutover {--at=}';

    protected $description = '锁定返利切换时间并初始化三类扫描游标';

    public function handle(ConfigService $configs, Sub2ApiReadRepository $read): int
    {
        $timezone = config('ledger.timezone', 'Asia/Shanghai');
        $config = $configs->get();
        $at = trim((string) $this->option('at'));
        $requested = $at === '' && $config->rebate_cutover_at
            ? CarbonImmutable::instance($config->rebate_cutover_at)
            : CarbonImmutable::parse($at ?: 'now', $timezone);
        $requested = $requested->startOfSecond();
        if ($config->rebate_cutover_at && $at !== '' && ! $config->rebate_cutover_at->equalTo($requested)) {
            $this->error('返利切换时间已锁定：'.$config->rebate_cutover_at->timezone($timezone)->format('Y-m-d H:i:s'));

            return self::FAILURE;
        }

        $cursor = $read->rebateCutoverCursor($requested);
        $cursors = collect(RebateScanService::SOURCES)
            ->mapWithKeys(fn (string $source): array => [$source => $cursor])
            ->all();

        DB::transaction(function () use ($configs, $config, $requested, $cursors): void {
            if ($config->rebate_cutover_at === null) {
                $configs->update(['rebate_cutover_at' => $requested]);
            }

            foreach ($cursors as $source => $cursor) {
                RebateScanCursor::query()->firstOrCreate(
                    ['source_type' => $source],
                    ['cursor_value' => $cursor, 'cursor_at' => now()],
                );
            }
        });

        $this->info('返利切换时间：'.$requested->format('Y-m-d H:i:s'));
        foreach ($cursors as $source => $cursor) {
            $this->line("{$source}: {$cursor}");
        }

        return self::SUCCESS;
    }
}
