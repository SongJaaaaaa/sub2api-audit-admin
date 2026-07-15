<?php

namespace App\Console\Commands;

use App\Services\Migration\AuditSqliteToPostgresMigrator;
use App\Services\Migration\MigrationException;
use App\Services\Migration\SqliteSourceConnectionFactory;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Throwable;

class MigrateSqliteToPostgresCommand extends Command
{
    protected $signature = 'audit:migrate-sqlite-to-postgres
        {--source= : 审计 SQLite 文件绝对路径}
        {--dry-run : 只检查并打印迁移摘要}
        {--commit : 在单个事务中执行迁移}';

    protected $description = '将本地审计 SQLite 的持久业务数据一次性迁移到 PostgreSQL';

    public function handle(
        DatabaseManager $db,
        SqliteSourceConnectionFactory $factory,
        AuditSqliteToPostgresMigrator $migrator,
    ): int {
        $name = 'audit_migration_source';

        try {
            $commit = $this->mode();
            $sourcePath = $this->sourcePath();
            $target = $db->connection();
            if ($target->getDriverName() !== 'pgsql') {
                throw new MigrationException('目标连接必须是 PostgreSQL，请先设置 DB_CONNECTION=pgsql。');
            }

            $beforeHash = $factory->hashImmutableBackup($sourcePath);
            $source = $factory->open($sourcePath, $name);
            $verify = fn () => $factory->assertUnchanged($sourcePath, $beforeHash);
            $report = $migrator->run($source, $target, $commit, $commit ? $verify : null);
            $factory->close($name);
            if (! $commit) {
                $factory->assertUnchanged($sourcePath, $beforeHash);
            }

            $this->line('源文件 SHA-256：'.$beforeHash);
            $this->table(
                ['表', '源行数', '目标行数'],
                collect($report['tables'])->map(fn (array $row, string $table): array => [
                    $table,
                    $row['source_count'],
                    $row['target_count'],
                ])->values()->all(),
            );
            $this->line('明确跳过：'.implode(', ', $report['excluded']));
            $this->info($commit ? '审计 SQLite 已迁移并完成行数、金额与序列校验。' : 'Dry run 完成，未写入任何数据。');

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
