<?php

namespace App\Console\Commands;

use App\Services\Migration\LegacyRebateImporter;
use App\Services\Migration\MigrationException;
use App\Services\Migration\SqliteSourceConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Throwable;

class ImportLegacyRebateCommand extends Command
{
    protected $signature = 'rebate:import-legacy
        {--source= : 旧返利 SQLite 文件绝对路径}
        {--dry-run : 只检查并打印导入摘要}
        {--commit : 在单个事务中执行导入}';

    protected $description = '导入旧返利用户、一级关系、配置、余额和只读提现历史';

    public function handle(
        DatabaseManager $db,
        SqliteSourceConnectionFactory $factory,
        LegacyRebateImporter $importer,
    ): int {
        $name = 'legacy_rebate_source';

        try {
            $commit = $this->mode();
            $path = $this->sourcePath();
            $target = $db->connection();
            if ($target->getDriverName() !== 'pgsql') {
                throw new MigrationException('目标连接必须是 PostgreSQL，请先设置 DB_CONNECTION=pgsql。');
            }

            $beforeHash = $factory->hashImmutableBackup($path);
            $source = $factory->open($path, $name);
            $verify = fn () => $factory->assertUnchanged($path, $beforeHash);
            $report = $importer->run($source, $target, $beforeHash, $commit, null, $commit ? $verify : null);
            $factory->close($name);
            if (! $commit) {
                $factory->assertUnchanged($path, $beforeHash);
            }

            $this->line('源文件 SHA-256：'.$beforeHash);
            $this->table(['项目', '数量'], collect($report['counts'])->map(fn (int $count, string $key): array => [$key, $count])->values()->all());
            $this->table(['金额项目', '合计'], collect($report['totals'])->map(fn (string $amount, string $key): array => [$key, $amount])->values()->all());
            $this->line('切换时间（UTC）：'.$report['cutover_at']);
            $this->info($commit ? '旧返利数据已导入，历史提现已锁定为只读且不可重放。' : 'Dry run 完成，未写入任何数据。');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $factory->close($name);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function mode(): bool
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');
        if ($dryRun === $commit) {
            throw new MigrationException('必须且只能指定 --dry-run 或 --commit。');
        }

        return $commit;
    }

    private function sourcePath(): string
    {
        $path = trim((string) $this->option('source'));
        $real = $path === '' ? false : realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new MigrationException('必须通过 --source 提供可读的 SQLite 文件绝对路径。');
        }

        return $real;
    }
}
