<?php

namespace Tests\Feature;

use App\Services\Migration\AuditSqliteToPostgresMigrator;
use App\Services\Migration\MigrationException;
use App\Services\Migration\SqliteSourceConnectionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class AuditMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_sqlite_rows_ids_and_amounts_are_preserved(): void
    {
        $path = $this->sqlitePath('audit');
        $pdo = $this->pdo($path);
        $pdo->exec(<<<'SQL'
            CREATE TABLE admins (
                id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL,
                password TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE ledger_adjustments (
                id INTEGER PRIMARY KEY, ledger_no TEXT NOT NULL, idempotency_key TEXT NOT NULL,
                sub2api_user_id INTEGER NOT NULL, sub2api_user_email TEXT, operation TEXT NOT NULL,
                amount NUMERIC NOT NULL, cash_amount NUMERIC NOT NULL, gift_quota_amount NUMERIC NOT NULL,
                before_balance NUMERIC, after_balance NUMERIC, status TEXT NOT NULL, adjust_reason TEXT NOT NULL,
                admin_notes TEXT, sub2api_notes TEXT, exception_reason TEXT, sub2api_request TEXT,
                sub2api_response TEXT, confirm_response TEXT, created_by INTEGER, called_at TEXT,
                confirmed_at TEXT, created_at TEXT, updated_at TEXT, sub2api_source_id INTEGER
            );
            CREATE TABLE rebate_users (id INTEGER PRIMARY KEY, email TEXT NOT NULL);
            INSERT INTO admins VALUES (7, '管理员', 'admin@example.com', 'hash', 'active', '2026-07-01 00:00:00', '2026-07-01 00:00:00');
            INSERT INTO ledger_adjustments VALUES (
                11, 'ADJ-11', 'idem-11', 89, 'test@example.com', 'increment',
                20.50, 15.25, 5.25, 1, 21.5, 'succeeded', '测试迁移',
                NULL, NULL, NULL, NULL, NULL, NULL, 7, NULL,
                '2026-07-01 01:00:00', '2026-07-01 00:59:00', '2026-07-01 01:00:00', 101
            );
            SQL);
        unset($pdo);

        $factory = app(SqliteSourceConnectionFactory::class);
        $source = $factory->open($path, 'test_audit_source');
        $target = DB::connection();
        $migrator = app(AuditSqliteToPostgresMigrator::class);

        $dryRun = $migrator->run($source, $target, false);
        $this->assertSame(1, $dryRun['tables']['ledger_adjustments']['source_count']);
        $this->assertContains('rebate_users', $dryRun['excluded']);
        $this->assertDatabaseCount('ledger_adjustments', 0);

        try {
            $migrator->run($source, $target, true, function (): void {
                throw new MigrationException('源文件已变化');
            });
            $this->fail('提交前源文件校验失败时仍提交了审计数据');
        } catch (MigrationException $e) {
            $this->assertSame('源文件已变化', $e->getMessage());
        }
        $this->assertDatabaseCount('admins', 0);
        $this->assertDatabaseCount('ledger_adjustments', 0);

        $report = $migrator->run($source, $target, true);
        $this->assertSame('20.5', $report['money']['ledger_adjustments.amount']['source']);
        $this->assertDatabaseHas('admins', ['id' => 7, 'name' => '管理员']);
        $this->assertDatabaseHas('ledger_adjustments', [
            'id' => 11,
            'amount' => '20.50',
            'cash_amount' => '15.25',
            'gift_quota_amount' => '5.25',
        ]);

        $factory->close('test_audit_source');
        @unlink($path);
    }

    public function test_sqlite_source_with_sidecar_files_is_rejected(): void
    {
        $path = $this->sqlitePath('wal');
        $pdo = $this->pdo($path);
        unset($pdo);
        file_put_contents($path.'-wal', 'not-checkpointed');

        try {
            app(SqliteSourceConnectionFactory::class)->open($path, 'unsafe_source');
            $this->fail('存在 WAL 旁路文件时仍打开了 SQLite 备份');
        } catch (MigrationException $e) {
            $this->assertStringContainsString('存在旁路文件 -wal', $e->getMessage());
        } finally {
            @unlink($path.'-wal');
            @unlink($path);
        }
    }

    private function sqlitePath(string $name): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$name.'-'.bin2hex(random_bytes(6)).'.sqlite';
    }

    private function pdo(string $path): PDO
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
