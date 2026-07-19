<?php

namespace App\Services\Sub2Api;

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
            ->selectSub(
                $this->db()->table('usage_logs')
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('usage_logs.user_id', 'users.id'),
                'last_used_at',
            )
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

        $stats = (clone $query)->select([])->selectRaw(
            'COUNT(*) as user_count, COALESCE(SUM(balance), 0) as balance_total, '
            .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count, '
            .'SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) as disabled_count, '
            .'SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END) as negative_balance_count, '
            .'SUM(CASE WHEN balance = 0 THEN 1 ELSE 0 END) as zero_balance_count',
            ['active', 'active'],
        )->first();
        $total = (int) $stats->user_count;
        $balanceTotal = (float) $stats->balance_total;
        $summary = [
            'user_count' => $total,
            'active_count' => (int) $stats->active_count,
            'disabled_count' => (int) $stats->disabled_count,
            'balance_total' => $this->decimal($balanceTotal, 2),
            'average_balance' => $this->decimal($total > 0 ? $balanceTotal / $total : 0, 2),
            'negative_balance_count' => (int) $stats->negative_balance_count,
            'zero_balance_count' => (int) $stats->zero_balance_count,
        ];
        $sortBy = (string) ($filters['sort_by'] ?? '');
        $sortOrder = (string) ($filters['sort_order'] ?? '');
        if ($sortBy === 'balance' && in_array($sortOrder, ['asc', 'desc'], true)) {
            $query->orderBy('balance', $sortOrder)->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $rows = $query->forPage($page, $pageSize)->get();
        $items = $rows
            ->map(fn ($row): array => $this->userRow($row, $row->last_used_at))
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
