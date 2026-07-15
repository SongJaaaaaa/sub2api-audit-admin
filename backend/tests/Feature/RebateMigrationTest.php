<?php

namespace Tests\Feature;

use App\Services\Migration\AuditSqliteToPostgresMigrator;
use App\Services\Migration\LegacyRebateImporter;
use App\Services\Migration\MigrationException;
use App\Services\Migration\SqliteSourceConnectionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class RebateMigrationTest extends TestCase
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

    public function test_legacy_rebate_import_preserves_expected_totals_and_read_only_history(): void
    {
        $path = $this->sqlitePath('legacy');
        $pdo = $this->pdo($path);
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY, username TEXT, email TEXT, status TEXT NOT NULL,
                sub2api_aff_code TEXT, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE referral_paths (
                id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, parent_user_id INTEGER,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE config_items (id INTEGER PRIMARY KEY, key TEXT NOT NULL, value TEXT NOT NULL);
            CREATE TABLE rebate_balances (
                id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, available_amount NUMERIC NOT NULL,
                frozen_amount NUMERIC NOT NULL, withdrawn_amount NUMERIC NOT NULL, created_at TEXT, updated_at TEXT
            );
            CREATE TABLE withdraw_records (
                id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, amount NUMERIC NOT NULL, status TEXT NOT NULL,
                type TEXT NOT NULL, remark TEXT, reviewed_by INTEGER, reviewed_at TEXT, paid_at TEXT,
                payout_time TEXT, payout_trade_no TEXT, created_at TEXT, updated_at TEXT
            );
            INSERT INTO users VALUES
                (1, NULL, 'one@example.com', 'active', 'AFF1', '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (2, '老王', 'two@example.com', 'active', 'AFF2', '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (89, NULL, 'child1@example.com', 'active', 'AFF89', '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (90, NULL, 'child2@example.com', 'active', 'AFF90', '2026-06-01 00:00:00', '2026-06-01 00:00:00');
            INSERT INTO referral_paths VALUES
                (1, 1, NULL, '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (2, 2, NULL, '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (3, 89, 2, '2026-06-01 00:00:00', '2026-06-01 00:00:00'),
                (4, 90, 2, '2026-06-01 00:00:00', '2026-06-01 00:00:00');
            INSERT INTO config_items VALUES
                (1, 'milestone.amount', '"100"'),
                (2, 'milestone.reward_amount', '"15"'),
                (3, 'milestone.max_times', '2'),
                (4, 'withdraw.min_amount', '"2"'),
                (5, 'withdraw.to_api_quota_rate', '"1"');
            INSERT INTO rebate_balances VALUES (1, 2, 24, 0, 6, '2026-06-01 00:00:00', '2026-06-28 00:41:17');
            INSERT INTO withdraw_records VALUES
                (1, 2, 2, 'paid', 'api_quota', NULL, NULL, NULL, '2026-06-27 23:25:17', NULL, NULL, '2026-06-27 23:25:17', '2026-06-27 23:25:17'),
                (2, 2, 2, 'paid', 'alipay', NULL, 1, '2026-06-28 00:41:12', '2026-06-28 00:41:17', NULL, NULL, '2026-06-28 00:34:02', '2026-06-28 00:41:17'),
                (3, 2, 2, 'paid', 'api_quota', '测试', NULL, NULL, '2026-06-28 00:35:54', NULL, NULL, '2026-06-28 00:35:54', '2026-06-28 00:35:54');
            SQL);
        unset($pdo);

        $hash = hash_file('sha256', $path);
        $factory = app(SqliteSourceConnectionFactory::class);
        $source = $factory->open($path, 'test_legacy_source');
        $importer = app(LegacyRebateImporter::class);
        $cutover = CarbonImmutable::parse('2026-07-15 00:00:00.987654', 'UTC');

        $dryRun = $importer->run($source, DB::connection(), $hash, false, $cutover);
        $this->assertSame(['users' => 4, 'referrals' => 4, 'balances' => 1, 'withdrawals' => 3], $dryRun['counts']);
        $this->assertSame(['available' => '24.00', 'frozen' => '0.00', 'withdrawn' => '6.00', 'withdrawals' => '6.00'], $dryRun['totals']);
        $this->assertDatabaseCount('rebate_users', 0);

        try {
            $importer->run($source, DB::connection(), $hash, true, $cutover, function (): void {
                throw new MigrationException('源文件已变化');
            });
            $this->fail('提交前源文件校验失败时仍提交了旧返利数据');
        } catch (MigrationException $e) {
            $this->assertSame('源文件已变化', $e->getMessage());
        }
        $this->assertDatabaseCount('rebate_users', 0);
        $this->assertDatabaseCount('rebate_balance_entries', 0);

        $report = $importer->run($source, DB::connection(), $hash, true, $cutover);
        $this->assertSame(['api_quota' => 2, 'alipay' => 1], $report['withdrawal_types']);
        $this->assertDatabaseCount('rebate_users', 4);
        $this->assertDatabaseCount('rebate_referrals', 4);
        $this->assertDatabaseCount('rebate_withdrawals', 3);
        $this->assertDatabaseCount('rebate_scan_cursors', 3);
        $this->assertDatabaseHas('rebate_balances', ['user_id' => 2, 'available_amount' => '24.00', 'withdrawn_amount' => '6.00']);
        $this->assertDatabaseHas('rebate_balance_entries', ['user_id' => 2, 'action' => 'legacy_opening', 'read_only' => 1]);
        $this->assertDatabaseHas('rebate_balance_entries', ['user_id' => 2, 'action' => 'legacy_withdrawn', 'read_only' => 1]);
        $this->assertSame(3, DB::table('rebate_withdrawals')->where('status', 'succeeded')->where('read_only', true)->count());
        $this->assertDatabaseHas('rebate_configs', [
            'id' => 1,
            'stage_amount' => '100.00',
            'stage_reward_amount' => '15.00',
            'withdraw_daily_limit' => 10,
            'withdraw_daily_amount_limit' => '0.00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rebate.legacy_import', 'target_type' => 'legacy_sqlite']);
        foreach (DB::table('rebate_scan_cursors')->pluck('cursor_value') as $cursor) {
            $this->assertSame([
                'at' => '2026-07-15T00:00:00.000000Z',
                'id' => 0,
            ], json_decode($cursor, true));
        }

        $factory->close('test_legacy_source');
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
