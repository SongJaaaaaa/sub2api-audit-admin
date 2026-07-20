<?php

namespace App\Services\Migration;

use Illuminate\Database\Connection;

class AuditSqliteToPostgresMigrator
{
    private const TABLES = [
        'admins',
        'users',
        'ledger_adjustments',
        'cash_entries',
        'gift_quota_entries',
        'operation_expenses',
        'attachments',
        'audit_logs',
        'system_settings',
        'profit_settlements',
        'profit_settlement_items',
    ];

    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'rebate_balance_entries',
        'rebate_balances',
        'rebate_configs',
        'rebate_events',
        'rebate_progress',
        'rebate_records',
        'rebate_referrals',
        'rebate_scan_cursors',
        'rebate_users',
        'rebate_withdrawals',
        'reconciliation_batches',
        'reconciliation_diffs',
        'sessions',
        'sqlite_sequence',
    ];

    private const MONEY_COLUMNS = [
        'ledger_adjustments' => ['amount', 'cash_amount', 'gift_quota_amount', 'before_balance', 'after_balance'],
        'cash_entries' => ['cash_amount'],
        'gift_quota_entries' => ['quota_amount'],
        'operation_expenses' => ['amount'],
        'profit_settlements' => ['income_total', 'expense_total', 'profit_total'],
        'profit_settlement_items' => ['amount'],
    ];

    public function run(Connection $source, Connection $target, bool $commit, ?callable $beforeCommit = null): array
    {
        if ($source->getDriverName() !== 'sqlite') {
            throw new MigrationException('审计迁移源连接必须是 SQLite。');
        }

        $tables = $this->sourceTables($source);
        $unknown = array_values(array_diff($tables, self::TABLES, self::EXCLUDED_TABLES));
        if ($unknown !== []) {
            throw new MigrationException('发现未纳入迁移的业务表：'.implode(', ', $unknown));
        }

        $report = $this->inspect($source, $target, $tables);
        if (! $commit) {
            return $report;
        }

        $target->transaction(function () use ($source, $target, $report, $beforeCommit): void {
            foreach ($report['tables'] as $table => $item) {
                if ($item['source_count'] === 0) {
                    continue;
                }

                $this->copyTable($source, $target, $table, $item['columns']);
            }

            foreach (array_keys($report['tables']) as $table) {
                $this->resetSequence($target, $table);
            }

            $this->verify($source, $target, $report);
            if ($beforeCommit !== null) {
                $beforeCommit();
            }
        });

        return $this->targetReport($target, $report);
    }

    private function targetReport(Connection $target, array $report): array
    {
        foreach ($report['tables'] as $table => $item) {
            $report['tables'][$table]['target_count'] = $target->table($table)->count();
        }
        foreach ($report['money'] as $key => $amounts) {
            [$table, $column] = explode('.', $key, 2);
            $report['money'][$key]['target'] = $this->sum($target, $table, $column);
        }

        return $report;
    }

    private function inspect(Connection $source, Connection $target, array $sourceTables): array
    {
        $report = ['tables' => [], 'money' => [], 'excluded' => []];
        $sourceSchema = $source->getSchemaBuilder();
        $targetSchema = $target->getSchemaBuilder();

        foreach (self::EXCLUDED_TABLES as $table) {
            if (in_array($table, $sourceTables, true)) {
                $report['excluded'][] = $table;
            }
        }

        foreach (self::TABLES as $table) {
            if (! in_array($table, $sourceTables, true)) {
                continue;
            }
            if (! $targetSchema->hasTable($table)) {
                throw new MigrationException("PostgreSQL 目标库缺少表：{$table}");
            }

            $sourceCols = $sourceSchema->getColumnListing($table);
            $targetCols = $targetSchema->getColumnListing($table);
            $missing = array_values(array_diff($sourceCols, $targetCols));
            if ($missing !== []) {
                throw new MigrationException("目标表 {$table} 缺少源字段：".implode(', ', $missing));
            }

            $sourceCount = $source->table($table)->count();
            $targetCount = $target->table($table)->count();
            if ($sourceCount > 0 && $targetCount > 0) {
                throw new MigrationException("目标表 {$table} 已有 {$targetCount} 条数据，不能覆盖迁移。");
            }

            $report['tables'][$table] = [
                'source_count' => $sourceCount,
                'target_count' => $targetCount,
                'columns' => $sourceCols,
            ];

            foreach (self::MONEY_COLUMNS[$table] ?? [] as $column) {
                if (! in_array($column, $sourceCols, true)) {
                    continue;
                }
                $report['money']["{$table}.{$column}"] = [
                    'source' => $this->sum($source, $table, $column),
                    'target' => $this->sum($target, $table, $column),
                ];
            }
        }

        return $report;
    }

    private function copyTable(Connection $source, Connection $target, string $table, array $columns): void
    {
        $order = in_array('id', $columns, true) ? 'id' : $columns[0];
        $source->table($table)->orderBy($order)->chunk(500, function ($rows) use ($target, $table, $columns): void {
            $allowed = array_flip($columns);
            $data = $rows->map(fn (object $row): array => array_intersect_key((array) $row, $allowed))->all();
            $target->table($table)->insert($data);
        });
    }

    private function verify(Connection $source, Connection $target, array $report): void
    {
        foreach ($report['tables'] as $table => $item) {
            $targetCount = $target->table($table)->count();
            if ($targetCount !== $item['source_count']) {
                throw new MigrationException("表 {$table} 行数校验失败：源 {$item['source_count']}，目标 {$targetCount}");
            }
        }

        foreach ($report['money'] as $key => $amounts) {
            [$table, $column] = explode('.', $key, 2);
            $targetTotal = $this->sum($target, $table, $column);
            if ($amounts['source'] !== $targetTotal) {
                throw new MigrationException("金额 {$key} 校验失败：源 {$amounts['source']}，目标 {$targetTotal}");
            }
        }
    }

    private function resetSequence(Connection $target, string $table): void
    {
        if ($target->getDriverName() !== 'pgsql' || ! $target->getSchemaBuilder()->hasColumn($table, 'id')) {
            return;
        }

        $seq = $target->selectOne("select pg_get_serial_sequence(?, 'id') as seq", [$table])?->seq;
        if (! is_string($seq) || $seq === '') {
            return;
        }

        $max = $target->table($table)->max('id');
        $target->statement('select setval(cast(? as regclass), ?, ?)', [$seq, $max ?? 1, $max !== null]);
    }

    private function sourceTables(Connection $source): array
    {
        return array_map(
            static fn (object $row): string => (string) $row->name,
            $source->select("select name from sqlite_master where type = 'table' order by name"),
        );
    }

    private function sum(Connection $conn, string $table, string $column): string
    {
        $grammar = $conn->getQueryGrammar();
        $sql = 'select cast(coalesce(sum('.$grammar->wrap($column).'), 0) as text) as total from '.$grammar->wrapTable($table);
        $value = (string) ($conn->selectOne($sql)?->total ?? '0');

        return $this->decimal($value);
    }

    private function decimal(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $match)) {
            throw new MigrationException("无法校验金额：{$value}");
        }

        $int = ltrim($match[2], '0');
        $int = $int === '' ? '0' : $int;
        $fraction = rtrim($match[3] ?? '', '0');
        $sign = $match[1] === '-' && ($int !== '0' || $fraction !== '') ? '-' : '';

        return $sign.$int.($fraction === '' ? '' : ".{$fraction}");
    }
}
