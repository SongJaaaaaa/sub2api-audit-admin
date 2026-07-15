<?php

namespace App\Services\Migration;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use JsonException;

class LegacyRebateImporter
{
    private const SOURCE = 'sub2rebate_sqlite';

    private const REQUIRED_SOURCE_COLUMNS = [
        'users' => ['id', 'username', 'email', 'status', 'sub2api_aff_code', 'created_at', 'updated_at'],
        'referral_paths' => ['id', 'user_id', 'parent_user_id', 'created_at', 'updated_at'],
        'config_items' => ['key', 'value'],
        'rebate_balances' => ['id', 'user_id', 'available_amount', 'frozen_amount', 'withdrawn_amount', 'created_at', 'updated_at'],
        'withdraw_records' => ['id', 'user_id', 'amount', 'status', 'type', 'created_at', 'updated_at'],
    ];

    private const TARGET_TABLES = [
        'rebate_users',
        'rebate_referrals',
        'rebate_configs',
        'rebate_scan_cursors',
        'rebate_balances',
        'rebate_balance_entries',
        'rebate_withdrawals',
        'audit_logs',
    ];

    public function run(
        Connection $source,
        Connection $target,
        string $sourceHash,
        bool $commit,
        ?CarbonImmutable $cutoverAt = null,
        ?callable $beforeCommit = null,
    ): array {
        if ($source->getDriverName() !== 'sqlite') {
            throw new MigrationException('旧返利源连接必须是 SQLite。');
        }
        if (! preg_match('/^[a-f0-9]{64}$/i', $sourceHash)) {
            throw new MigrationException('源文件 SHA-256 无效。');
        }

        $this->validateSchema($source, $target);
        $this->ensureEmptyTarget($target);
        $cutoverAt = ($cutoverAt ?? CarbonImmutable::now('UTC'))->startOfSecond();
        $data = $this->readSource($source, strtolower($sourceHash), $cutoverAt);

        if (! $commit) {
            return $data['report'];
        }

        $target->transaction(function () use ($target, $data, $beforeCommit): void {
            $this->write($target, $data);
            $this->verify($target, $data['report']);
            if ($beforeCommit !== null) {
                $beforeCommit();
            }
        });

        return $data['report'];
    }

    private function validateSchema(Connection $source, Connection $target): void
    {
        $sourceSchema = $source->getSchemaBuilder();
        foreach (self::REQUIRED_SOURCE_COLUMNS as $table => $columns) {
            if (! $sourceSchema->hasTable($table)) {
                throw new MigrationException("旧返利 SQLite 缺少表：{$table}");
            }
            $missing = array_values(array_diff($columns, $sourceSchema->getColumnListing($table)));
            if ($missing !== []) {
                throw new MigrationException("旧返利表 {$table} 缺少字段：".implode(', ', $missing));
            }
        }

        $targetSchema = $target->getSchemaBuilder();
        foreach (self::TARGET_TABLES as $table) {
            if (! $targetSchema->hasTable($table)) {
                throw new MigrationException("目标库缺少表：{$table}");
            }
        }
    }

    private function ensureEmptyTarget(Connection $target): void
    {
        foreach ([
            'rebate_users',
            'rebate_referrals',
            'rebate_configs',
            'rebate_scan_cursors',
            'rebate_balances',
            'rebate_balance_entries',
            'rebate_withdrawals',
        ] as $table) {
            $count = $target->table($table)->count();
            if ($count > 0) {
                throw new MigrationException("目标表 {$table} 已有 {$count} 条数据，旧返利导入只能执行一次。");
            }
        }
    }

    private function readSource(Connection $source, string $hash, CarbonImmutable $cutoverAt): array
    {
        $users = $source->table('users')->orderBy('id')->get()->map(function (object $row) use ($hash): array {
            return [
                'id' => (int) $row->id,
                'username' => $this->nullable($row->username),
                'email' => $this->nullable($row->email),
                'status' => (string) $row->status,
                'aff_code' => $this->nullable($row->sub2api_aff_code),
                'last_synced_at' => null,
                'legacy_source' => self::SOURCE,
                'legacy_source_id' => (string) $row->id,
                'source_hash' => $hash,
                'read_only' => false,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->all();

        $userIds = array_fill_keys(array_column($users, 'id'), true);
        $referrals = $source->table('referral_paths')->orderBy('id')->get()->map(function (object $row) use ($userIds): array {
            $userId = (int) $row->user_id;
            $parentId = $row->parent_user_id === null ? null : (int) $row->parent_user_id;
            if (! isset($userIds[$userId]) || ($parentId !== null && ! isset($userIds[$parentId]))) {
                throw new MigrationException("推荐关系 {$row->id} 引用了不存在的用户。");
            }

            return [
                'id' => (int) $row->id,
                'user_id' => $userId,
                'parent_user_id' => $parentId,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->all();

        $rawConfig = $source->table('config_items')->pluck('value', 'key')->all();
        $config = $this->config($rawConfig, $cutoverAt);
        $cursors = $this->cursors($cutoverAt);
        $balances = $source->table('rebate_balances')
            ->selectRaw('id, user_id, cast(available_amount as text) as available_amount, cast(frozen_amount as text) as frozen_amount, cast(withdrawn_amount as text) as withdrawn_amount, created_at, updated_at')
            ->orderBy('id')->get()->map(function (object $row) use ($userIds): array {
                if (! isset($userIds[(int) $row->user_id])) {
                    throw new MigrationException("返利余额 {$row->id} 引用了不存在的用户。");
                }
                if ($this->money($row->frozen_amount) !== '0.00') {
                    throw new MigrationException("用户 {$row->user_id} 仍有冻结余额，请先在旧系统处理完提现。");
                }

                return [
                    'id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'available_amount' => $this->money($row->available_amount),
                    'frozen_amount' => '0.00',
                    'withdrawn_amount' => $this->money($row->withdrawn_amount),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            })->all();

        $withdrawals = $source->table('withdraw_records')
            ->selectRaw('*, cast(amount as text) as amount_text')
            ->orderBy('id')->get()->map(function (object $row) use ($hash, $config, $userIds): array {
                if ((string) $row->status !== 'paid') {
                    throw new MigrationException("历史提现 {$row->id} 不是 paid 状态，请先人工确认。");
                }
                if (! isset($userIds[(int) $row->user_id])) {
                    throw new MigrationException("历史提现 {$row->id} 引用了不存在的用户。");
                }

                $amount = $this->money($row->amount_text);
                $quota = (string) BigDecimal::of($amount)
                    ->multipliedBy($config['withdraw_to_api_quota_rate'])
                    ->toScale(2, RoundingMode::UNNECESSARY);

                return [
                    'id' => (int) $row->id,
                    'withdrawal_no' => 'LEGACY-'.str_pad((string) $row->id, 8, '0', STR_PAD_LEFT),
                    'user_id' => (int) $row->user_id,
                    'amount' => $amount,
                    'quota_amount' => $quota,
                    'status' => 'succeeded',
                    'remark' => $this->nullable($row->remark ?? null),
                    'reviewed_by' => null,
                    'reject_reason' => null,
                    'exception_reason' => null,
                    'attempts' => 0,
                    'payout_reference' => $this->nullable($row->payout_trade_no ?? null),
                    'payout_response' => null,
                    'requested_at' => $row->created_at,
                    'reviewed_at' => $row->reviewed_at ?? $row->paid_at ?? $row->updated_at,
                    'processing_started_at' => null,
                    'completed_at' => $row->paid_at ?? $row->payout_time ?? $row->updated_at,
                    'legacy_source' => self::SOURCE,
                    'legacy_source_id' => (string) $row->id,
                    'source_hash' => $hash,
                    'read_only' => true,
                    'meta' => $this->json([
                        'legacy_type' => (string) $row->type,
                        'legacy_status' => (string) $row->status,
                        'legacy_reviewed_by' => $row->reviewed_by ?? null,
                    ]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            })->all();

        $report = [
            'source_hash' => $hash,
            'cutover_at' => $cutoverAt->toISOString(),
            'counts' => [
                'users' => count($users),
                'referrals' => count($referrals),
                'balances' => count($balances),
                'withdrawals' => count($withdrawals),
            ],
            'totals' => [
                'available' => $this->total($balances, 'available_amount'),
                'frozen' => $this->total($balances, 'frozen_amount'),
                'withdrawn' => $this->total($balances, 'withdrawn_amount'),
                'withdrawals' => $this->total($withdrawals, 'amount'),
            ],
            'withdrawal_types' => collect($withdrawals)->countBy(fn (array $row): string => (string) json_decode($row['meta'], true, 512, JSON_THROW_ON_ERROR)['legacy_type'])->all(),
            'config' => $config,
        ];

        return compact('users', 'referrals', 'config', 'cursors', 'balances', 'withdrawals', 'report');
    }

    private function config(array $raw, CarbonImmutable $cutoverAt): array
    {
        $localTime = $cutoverAt
            ->setTimezone(config('ledger.timezone', 'Asia/Shanghai'))
            ->format('Y-m-d H:i:s');

        return [
            'id' => 1,
            'milestone_amount' => $this->money($this->configValue($raw, 'milestone.amount', '100')),
            'milestone_reward_amount' => $this->money($this->configValue($raw, 'milestone.reward_amount', '15')),
            'milestone_max_times' => (int) $this->configValue($raw, 'milestone.max_times', 2),
            'stage_amount' => $this->money($this->configValue($raw, 'rebate.stage_amount', '100')),
            'stage_reward_amount' => $this->money($this->configValue($raw, 'rebate.stage_reward_amount', '15')),
            'withdraw_min_amount' => $this->money($this->configValue($raw, 'withdraw.min_amount', '2')),
            'withdraw_daily_limit' => (int) $this->configValue($raw, 'withdraw.api_quota_daily_limit', 10),
            'withdraw_daily_amount_limit' => $this->money($this->configValue($raw, 'withdraw.api_quota_daily_amount_limit', '0')),
            'withdraw_to_api_quota_rate' => $this->rate($this->configValue($raw, 'withdraw.to_api_quota_rate', '1')),
            'native_recharge_enabled' => (bool) $this->configValue($raw, 'rebate.sub2api_native_recharge_enabled', true),
            'redeem_enabled' => (bool) $this->configValue($raw, 'rebate.sub2api_redeem_enabled', true),
            'admin_adjust_enabled' => (bool) $this->configValue($raw, 'rebate.sub2api_admin_adjust_enabled', false),
            'rebate_cutover_at' => $localTime,
            'created_at' => $localTime,
            'updated_at' => $localTime,
        ];
    }

    private function cursors(CarbonImmutable $cutoverAt): array
    {
        $value = $this->json([
            'at' => $cutoverAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'id' => 0,
        ]);
        $localTime = $cutoverAt
            ->setTimezone(config('ledger.timezone', 'Asia/Shanghai'))
            ->format('Y-m-d H:i:s');

        return collect(['native_recharge', 'redeem', 'admin_adjustment'])
            ->map(fn (string $source): array => [
                'source_type' => $source,
                'cursor_value' => $value,
                'cursor_at' => $localTime,
                'meta' => null,
                'created_at' => $localTime,
                'updated_at' => $localTime,
            ])
            ->all();
    }

    private function write(Connection $target, array $data): void
    {
        $target->table('rebate_users')->insert($data['users']);
        $target->table('rebate_referrals')->insert($data['referrals']);
        $target->table('rebate_configs')->insert($data['config']);
        $target->table('rebate_scan_cursors')->insert($data['cursors']);

        foreach ($data['balances'] as $balance) {
            $target->table('rebate_balances')->insert($balance);
            $this->writeOpeningEntries($target, $balance, $data['report']['source_hash']);
        }

        if ($data['withdrawals'] !== []) {
            $target->table('rebate_withdrawals')->insert($data['withdrawals']);
        }

        $now = $data['config']['created_at'];
        $target->table('audit_logs')->insert([
            'admin_id' => null,
            'admin_name' => 'system',
            'action' => 'rebate.legacy_import',
            'target_type' => 'legacy_sqlite',
            'target_id' => null,
            'before_value' => null,
            'after_value' => $this->json($data['report']),
            'ip' => null,
            'user_agent' => 'artisan rebate:import-legacy',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['rebate_referrals', 'rebate_balances', 'rebate_balance_entries', 'rebate_withdrawals'] as $table) {
            $this->resetSequence($target, $table);
        }
    }

    private function writeOpeningEntries(Connection $target, array $balance, string $hash): void
    {
        $base = [
            'balance_id' => $balance['id'],
            'user_id' => $balance['user_id'],
            'frozen_before' => '0.00',
            'frozen_after' => '0.00',
            'business_type' => 'legacy_import',
            'source_hash' => $hash,
            'read_only' => true,
            'created_at' => $balance['updated_at'] ?? $balance['created_at'],
        ];

        if ($balance['available_amount'] !== '0.00') {
            $target->table('rebate_balance_entries')->insert(array_merge($base, [
                'action' => 'legacy_opening',
                'amount' => $balance['available_amount'],
                'available_before' => '0.00',
                'available_after' => $balance['available_amount'],
                'withdrawn_before' => '0.00',
                'withdrawn_after' => '0.00',
                'business_key' => "{$hash}:user:{$balance['user_id']}:available",
                'note' => '旧返利系统可用余额期初导入',
                'meta' => null,
                'legacy_source' => self::SOURCE,
                'legacy_source_id' => "balance:{$balance['id']}:available",
            ]));
        }

        if ($balance['withdrawn_amount'] !== '0.00') {
            $target->table('rebate_balance_entries')->insert(array_merge($base, [
                'action' => 'legacy_withdrawn',
                'amount' => $balance['withdrawn_amount'],
                'available_before' => $balance['available_amount'],
                'available_after' => $balance['available_amount'],
                'withdrawn_before' => '0.00',
                'withdrawn_after' => $balance['withdrawn_amount'],
                'business_key' => "{$hash}:user:{$balance['user_id']}:withdrawn",
                'note' => '旧返利系统已提现金额导入',
                'meta' => null,
                'legacy_source' => self::SOURCE,
                'legacy_source_id' => "balance:{$balance['id']}:withdrawn",
            ]));
        }
    }

    private function verify(Connection $target, array $report): void
    {
        $checks = [
            'users' => 'rebate_users',
            'referrals' => 'rebate_referrals',
            'balances' => 'rebate_balances',
            'withdrawals' => 'rebate_withdrawals',
        ];
        foreach ($checks as $key => $table) {
            $count = $target->table($table)->count();
            if ($count !== $report['counts'][$key]) {
                throw new MigrationException("{$table} 行数校验失败。");
            }
        }

        $balanceTotals = $target->table('rebate_balances')->selectRaw(
            'cast(coalesce(sum(available_amount), 0) as text) as available, '.
            'cast(coalesce(sum(frozen_amount), 0) as text) as frozen, '.
            'cast(coalesce(sum(withdrawn_amount), 0) as text) as withdrawn',
        )->first();
        foreach (['available', 'frozen', 'withdrawn'] as $key) {
            if ($this->money($balanceTotals->{$key}) !== $report['totals'][$key]) {
                throw new MigrationException("返利余额 {$key} 合计校验失败。");
            }
        }

        $withdrawTotal = $target->table('rebate_withdrawals')
            ->selectRaw('cast(coalesce(sum(amount), 0) as text) as total')
            ->first()?->total ?? '0';
        if ($this->money($withdrawTotal) !== $report['totals']['withdrawals']) {
            throw new MigrationException('历史提现金额合计校验失败。');
        }

        if ($target->table('rebate_scan_cursors')->count() !== 3) {
            throw new MigrationException('返利扫描游标初始化失败。');
        }
    }

    private function resetSequence(Connection $target, string $table): void
    {
        if ($target->getDriverName() !== 'pgsql') {
            return;
        }
        $seq = $target->selectOne("select pg_get_serial_sequence(?, 'id') as seq", [$table])?->seq;
        if (! is_string($seq) || $seq === '') {
            return;
        }
        $max = $target->table($table)->max('id');
        $target->statement('select setval(cast(? as regclass), ?, ?)', [$seq, $max ?? 1, $max !== null]);
    }

    private function configValue(array $items, string $key, mixed $default): mixed
    {
        if (! array_key_exists($key, $items)) {
            return $default;
        }

        $raw = $items[$key];
        try {
            return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $raw;
        }
    }

    private function money(mixed $value): string
    {
        try {
            return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw new MigrationException("无效的金额：{$value}");
        }
    }

    private function rate(mixed $value): string
    {
        try {
            return (string) BigDecimal::of((string) $value)->toScale(4, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw new MigrationException("无效的换算比例：{$value}");
        }
    }

    private function total(array $rows, string $key): string
    {
        $total = BigDecimal::zero();
        foreach ($rows as $row) {
            $total = $total->plus($row[$key]);
        }

        return (string) $total->toScale(2, RoundingMode::UNNECESSARY);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
