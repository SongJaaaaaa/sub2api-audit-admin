<?php

namespace App\Services\Sub2Api;

use App\Models\LedgerAdjustment;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use App\Support\Sub2ApiNoteTag;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class Sub2ApiReadRepository
{
    private function db(): ConnectionInterface
    {
        return DB::connection(config('sub2api.db_connection', 'sub2api'));
    }

    public function users(array $filters, int $page, int $pageSize): array
    {
        $query = $this->db()
            ->table('users')
            ->select([
                'id',
                'email',
                'username',
                'role',
                'balance',
                'total_recharged',
                'status',
                'created_at',
                'updated_at',
            ])
            ->whereNull('deleted_at');

        $kw = trim((string) ($filters['keyword'] ?? ''));
        if ($kw !== '') {
            $query->where(function ($sub) use ($kw): void {
                $sub->where('email', 'like', "%{$kw}%")
                    ->orWhere('username', 'like', "%{$kw}%");
            });
        }

        $userFilter = trim((string) ($filters['user_filter'] ?? ''));
        if ($userFilter === 'zero_balance') {
            $query->where('balance', 0);
        } elseif ($userFilter === 'negative_balance') {
            $query->where('balance', '<', 0);
        } elseif ($userFilter === 'disabled') {
            $query->where('status', '!=', 'active');
        }

        $lastUsedStart = trim((string) ($filters['last_used_start'] ?? ''));
        $lastUsedEnd = trim((string) ($filters['last_used_end'] ?? ''));
        if ($lastUsedStart !== '' && $lastUsedEnd !== '') {
            $range = ChinaDateRange::make($lastUsedStart, $lastUsedEnd);
            [$start, $end] = $this->remoteBounds($range->utcStart, $range->utcEndExclusive);
            $usedUserIds = $this->db()
                ->table('usage_logs')
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('MAX(created_at) >= ? AND MAX(created_at) < ?', [$start, $end]);
            $query->whereIn('id', $usedUserIds);
        }

        $total = (clone $query)->count();
        $balanceTotal = (float) (clone $query)->sum('balance');
        $summary = [
            'user_count' => $total,
            'active_count' => (clone $query)->where('status', 'active')->count(),
            'disabled_count' => (clone $query)->where('status', '!=', 'active')->count(),
            'balance_total' => $this->decimal($balanceTotal, 2),
            'average_balance' => $this->decimal($total > 0 ? $balanceTotal / $total : 0, 2),
            'negative_balance_count' => (clone $query)->where('balance', '<', 0)->count(),
            'zero_balance_count' => (clone $query)->where('balance', 0)->count(),
        ];
        $rows = $query
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get();
        $ids = $rows->pluck('id')->all();
        $lastUsed = $ids === []
            ? collect()
            : $this->db()
                ->table('usage_logs')
                ->whereIn('user_id', $ids)
                ->selectRaw('user_id, MAX(created_at) as last_used_at')
                ->groupBy('user_id')
                ->pluck('last_used_at', 'user_id');
        $items = $rows
            ->map(fn ($row): array => $this->userRow($row, $lastUsed->get($row->id)))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }

    public function user(int $id): ?array
    {
        $row = $this->db()
            ->table('users')
            ->select([
                'id',
                'email',
                'username',
                'role',
                'balance',
                'total_recharged',
                'status',
                'created_at',
                'updated_at',
            ])
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row ? $this->userRow($row) : null;
    }

    public function affiliateUser(int $id): ?array
    {
        $row = $this->db()
            ->table('users as u')
            ->leftJoin('user_affiliates as ua', 'ua.user_id', '=', 'u.id')
            ->select([
                'u.id',
                'u.username',
                'u.email',
                'u.status',
                'ua.aff_code',
                'ua.inviter_id as parent_user_id',
            ])
            ->where('u.id', $id)
            ->whereNull('u.deleted_at')
            ->first();

        return $row ? $this->affiliateRow($row) : null;
    }

    public function affiliateChildren(int $parentId, int $page = 1, int $pageSize = 20): array
    {
        $query = $this->db()
            ->table('users as u')
            ->join('user_affiliates as ua', 'ua.user_id', '=', 'u.id')
            ->select([
                'u.id',
                'u.username',
                'u.email',
                'u.status',
                'ua.aff_code',
                'ua.inviter_id as parent_user_id',
            ])
            ->where('ua.inviter_id', $parentId)
            ->whereNull('u.deleted_at');

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('u.id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn (object $row): array => $this->affiliateRow($row))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function rebateEvents(string $sourceType, ?string $cursor, int $limit = 200): array
    {
        $limit = min(max($limit, 1), 1000);

        return match ($sourceType) {
            'native_recharge' => $this->nativeRechargeEvents($cursor, $limit),
            'redeem' => $this->balanceRedeemCursorEvents($cursor, $limit),
            'admin_adjustment' => $this->adminAdjustmentCursorEvents($cursor, $limit),
            default => throw new \InvalidArgumentException('未知返利扫描来源：'.$sourceType),
        };
    }

    public function latestRebateCursor(string $sourceType): ?string
    {
        [$table, $timeColumn, $type] = match ($sourceType) {
            'native_recharge' => ['payment_orders', 'completed_at', 'balance'],
            'redeem' => ['redeem_codes', 'used_at', 'balance'],
            'admin_adjustment' => ['redeem_codes', 'used_at', 'admin_balance'],
            default => throw new \InvalidArgumentException('未知返利扫描来源：'.$sourceType),
        };
        $query = $this->db()->table($table)
            ->select(['id', $timeColumn])
            ->whereNotNull($timeColumn)
            ->whereRaw('LOWER(status) = ?', [$table === 'payment_orders' ? 'completed' : 'used']);
        if ($table === 'payment_orders') {
            $query->whereRaw('LOWER(order_type) = ?', [$type]);
        } else {
            $query->whereRaw('LOWER(type) = ?', [$type]);
        }
        $row = $query->orderByDesc($timeColumn)->orderByDesc('id')->first();

        return $row ? $this->encodeCursor($row->{$timeColumn}, (int) $row->id) : null;
    }

    public function rebateCutoverCursor(CarbonImmutable $cutoverAt): string
    {
        $time = $this->db()->getDriverName() === 'sqlite'
            ? $cutoverAt->utc()->format('Y-m-d H:i:s')
            : ChinaTime::utcText($cutoverAt);

        return $this->encodeCursor($time, 0);
    }

    public function activeUserBalanceSnapshot(): array
    {
        $query = $this->db()
            ->table('users')
            ->where('role', 'user')
            ->where('status', 'active')
            ->whereNull('deleted_at');

        return [
            'active_user_count' => (int) (clone $query)->count(),
            'active_user_balance' => $this->decimal((clone $query)->sum('balance'), 8),
            'total_recharged' => $this->decimal($this->db()->table('users')->whereNull('deleted_at')->sum('total_recharged'), 8),
            'as_of' => now(config('ledger.timezone', 'Asia/Shanghai'))->format(ChinaTime::FORMAT),
        ];
    }

    public function findAdminAdjustmentSources(int $userId, string $idempotencyKey): array
    {
        return $this->db()
            ->table('redeem_codes')
            ->select(['id', 'used_by', 'value', 'notes', 'used_at', 'created_at'])
            ->whereRaw('LOWER(type) = ?', ['admin_balance'])
            ->whereRaw('LOWER(status) = ?', ['used'])
            ->where('used_by', $userId)
            ->where('notes', 'like', '%idempotency_key='.$idempotencyKey.'%')
            ->orderBy('id')
            ->get()
            ->filter(fn ($row): bool => Sub2ApiNoteTag::idempotencyKey($row->notes ?? null) === $idempotencyKey)
            ->map(fn ($row): array => $this->redeemRow($row))
            ->values()
            ->all();
    }

    public function adminAdjustmentEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return $this->redeemEvents('admin_balance', $startUtc, $endUtc, 'admin_adjustment');
    }

    public function balanceRedeemEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        return $this->redeemEvents('balance', $startUtc, $endUtc, 'balance_redeem');
    }

    public function paymentOrderEvents(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        [$start, $end] = $this->remoteBounds($startUtc, $endUtc);

        return $this->db()
            ->table('payment_orders as po')
            ->leftJoin('users as u', 'u.id', '=', 'po.user_id')
            ->select([
                'po.id',
                'po.user_id',
                'po.user_email',
                'po.user_name',
                'po.amount',
                'po.out_trade_no',
                'po.completed_at',
                'po.created_at',
                'u.email as current_email',
                'u.username as current_username',
            ])
            ->whereRaw('LOWER(po.order_type) = ?', ['balance'])
            ->whereRaw('LOWER(po.status) = ?', ['completed'])
            ->whereNotNull('po.completed_at')
            ->where('po.amount', '!=', 0)
            ->where('po.completed_at', '>=', $start)
            ->where('po.completed_at', '<', $end)
            ->get()
            ->map(fn ($row): array => [
                'source' => 'payment_order',
                'remote_event_id' => (int) $row->id,
                'sub2api_user_id' => (int) $row->user_id,
                'user_email' => $row->current_email ?: $row->user_email,
                'username' => $row->current_username ?: $row->user_name,
                'value' => (string) $row->amount,
                'notes' => trim((string) $row->out_trade_no),
                'event_at' => $row->completed_at,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    private function redeemEvents(string $type, CarbonImmutable $startUtc, CarbonImmutable $endUtc, string $source): array
    {
        [$start, $end] = $this->remoteBounds($startUtc, $endUtc);

        return $this->db()
            ->table('redeem_codes as rc')
            ->leftJoin('users as u', 'u.id', '=', 'rc.used_by')
            ->select([
                'rc.id',
                'rc.used_by',
                'rc.value',
                'rc.notes',
                'rc.used_at',
                'rc.created_at',
                'u.email',
                'u.username',
            ])
            ->whereRaw('LOWER(rc.type) = ?', [$type])
            ->whereRaw('LOWER(rc.status) = ?', ['used'])
            ->whereNotNull('rc.used_by')
            ->whereNotNull('rc.used_at')
            ->where('rc.used_at', '>=', $start)
            ->where('rc.used_at', '<', $end)
            ->get()
            ->map(fn ($row): array => [
                'source' => $source,
                'remote_event_id' => (int) $row->id,
                'sub2api_user_id' => (int) $row->used_by,
                'user_email' => $row->email,
                'username' => $row->username,
                'value' => (string) $row->value,
                'notes' => $row->notes,
                'event_at' => $row->used_at,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    private function nativeRechargeEvents(?string $cursor, int $limit): array
    {
        $query = $this->db()
            ->table('payment_orders')
            ->select(['id', 'user_id', 'amount', 'out_trade_no', 'completed_at', 'created_at'])
            ->whereRaw('LOWER(order_type) = ?', ['balance'])
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->whereNotNull('completed_at')
            ->where('amount', '>', 0);
        $this->applyCursor($query, $cursor, 'completed_at');
        $rows = $query
            ->orderBy('completed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $this->cursorPage($rows, fn (object $row): array => [
            'source_id' => (string) $row->id,
            'user_id' => (int) $row->user_id,
            'amount' => $this->decimalAmount($row->amount),
            'happened_at' => (string) ($row->completed_at ?: $row->created_at),
            'payload' => [
                'out_trade_no' => $row->out_trade_no,
            ],
        ], $limit, 'completed_at');
    }

    private function balanceRedeemCursorEvents(?string $cursor, int $limit): array
    {
        $query = $this->db()
            ->table('redeem_codes')
            ->select(['id', 'used_by', 'value', 'notes', 'used_at', 'created_at'])
            ->whereRaw('LOWER(type) = ?', ['balance'])
            ->whereRaw('LOWER(status) = ?', ['used'])
            ->whereNotNull('used_by')
            ->whereNotNull('used_at')
            ->where('value', '>', 0);
        $this->applyCursor($query, $cursor, 'used_at');
        $rows = $query
            ->orderBy('used_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $this->cursorPage($rows, fn (object $row): array => [
            'source_id' => (string) $row->id,
            'user_id' => (int) $row->used_by,
            'amount' => $this->decimalAmount($row->value),
            'happened_at' => (string) ($row->used_at ?: $row->created_at),
            'payload' => [
                'notes' => $row->notes,
            ],
        ], $limit, 'used_at');
    }

    private function adminAdjustmentCursorEvents(?string $cursor, int $limit): array
    {
        $query = $this->db()
            ->table('redeem_codes')
            ->select(['id', 'used_by', 'value', 'notes', 'used_at', 'created_at'])
            ->whereRaw('LOWER(type) = ?', ['admin_balance'])
            ->whereRaw('LOWER(status) = ?', ['used'])
            ->whereNotNull('used_by')
            ->whereNotNull('used_at');
        $this->applyCursor($query, $cursor, 'used_at');
        $rows = $query
            ->orderBy('used_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ids = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $keys = $rows
            ->map(fn (object $row): ?string => Sub2ApiNoteTag::idempotencyKey($row->notes))
            ->filter()
            ->values()
            ->all();
        $adjustments = $rows->isEmpty()
            ? collect()
            : LedgerAdjustment::query()
                ->where(function ($query) use ($ids, $keys): void {
                    $query->whereIn('sub2api_source_id', $ids);
                    if ($keys !== []) {
                        $query->orWhereIn('idempotency_key', $keys);
                    }
                })
                ->get();
        $bySource = $adjustments->whereNotNull('sub2api_source_id')->keyBy('sub2api_source_id');
        $byKey = $adjustments->keyBy('idempotency_key');

        return $this->cursorPage($rows, function (object $row) use ($bySource, $byKey): ?array {
            $key = Sub2ApiNoteTag::idempotencyKey($row->notes);
            /** @var LedgerAdjustment|null $adj */
            $adj = $bySource->get((int) $row->id) ?: ($key ? $byKey->get($key) : null);

            if (
                ! $adj instanceof LedgerAdjustment
                || $adj->business_source === LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL
                || bccomp((string) $adj->cash_amount, '0', 2) <= 0
            ) {
                return null;
            }

            return [
                'source_id' => (string) $row->id,
                'user_id' => (int) $row->used_by,
                'amount' => (string) $adj->cash_amount,
                'happened_at' => (string) ($row->used_at ?: $row->created_at),
                'payload' => [
                    'remote_amount' => $this->decimalAmount($row->value),
                    'business_source' => $adj->business_source,
                    'business_id' => $adj->business_id,
                    'cash_amount' => (string) $adj->cash_amount,
                    'gift_quota_amount' => (string) $adj->gift_quota_amount,
                    'idempotency_key' => $adj->idempotency_key,
                ],
            ];
        }, $limit, 'used_at');
    }

    private function cursorPage(object $rows, callable $map, int $limit, string $timeColumn): array
    {
        $items = $rows
            ->map($map)
            ->filter(fn (?array $row): bool => $row !== null)
            ->values()
            ->all();
        $last = $rows->last();

        return [
            'items' => $items,
            'next_cursor' => $last ? $this->encodeCursor($last->{$timeColumn}, (int) $last->id) : null,
            'has_more' => $rows->count() === $limit,
        ];
    }

    private function applyCursor(mixed $query, ?string $cursor, string $timeColumn): void
    {
        $value = $this->decodeCursor($cursor);
        if ($value === null) {
            return;
        }

        $query->where(function ($nested) use ($timeColumn, $value): void {
            $nested->where($timeColumn, '>', $value['at'])
                ->orWhere(function ($sameTime) use ($timeColumn, $value): void {
                    $sameTime->where($timeColumn, $value['at'])->where('id', '>', $value['id']);
                });
        });
    }

    private function encodeCursor(mixed $time, int $id): string
    {
        return json_encode(['at' => (string) $time, 'id' => $id], JSON_THROW_ON_ERROR);
    }

    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $value = json_decode($cursor, true, 512, JSON_THROW_ON_ERROR);

        return ['at' => (string) $value['at'], 'id' => (int) $value['id']];
    }

    private function affiliateRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'username' => (string) ($row->username ?? ''),
            'email' => (string) ($row->email ?? ''),
            'status' => (string) ($row->status ?? ''),
            'aff_code' => isset($row->aff_code) ? (string) $row->aff_code : null,
            'parent_user_id' => is_numeric($row->parent_user_id ?? null) ? (int) $row->parent_user_id : null,
        ];
    }

    private function decimalAmount(mixed $value): string
    {
        $amount = trim((string) $value);
        $offset = str_starts_with($amount, '-') ? '-0.005' : '0.005';
        $rounded = bcadd($amount, $offset, 2);

        return $rounded === '-0.00' ? '0.00' : $rounded;
    }

    private function redeemRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'used_by' => (int) $row->used_by,
            'value' => (string) $row->value,
            'notes' => $row->notes,
            'used_at' => $row->used_at,
            'created_at' => $row->created_at,
        ];
    }

    private function remoteBounds(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        if ($this->db()->getDriverName() === 'sqlite') {
            return [
                $startUtc->utc()->format(ChinaTime::FORMAT),
                $endUtc->utc()->format(ChinaTime::FORMAT),
            ];
        }

        return [ChinaTime::utcText($startUtc), ChinaTime::utcText($endUtc)];
    }

    private function userRow(object $row, mixed $lastUsedAt = null): array
    {
        $item = (array) $row;
        $item['balance'] = $this->decimal($item['balance'], 8);
        $item['total_recharged'] = $this->decimal($item['total_recharged'], 8);
        $item['last_used_at'] = ChinaTime::fmtUtc($lastUsedAt);
        $item['created_at'] = ChinaTime::fmtUtc($item['created_at'] ?? null);
        $item['updated_at'] = ChinaTime::fmtUtc($item['updated_at'] ?? null);

        return $item;
    }

    private function decimal(mixed $value, int $scale): string
    {
        $text = number_format((float) $value, $scale, '.', '');

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }
}
