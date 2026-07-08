<?php

namespace App\Services\Sub2Api;

use App\Support\ChinaTime;
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

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn ($row): array => $this->userRow($row))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
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

    public function usageSummary(CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $query = $this->usageQuery($from, $to, $filters);
        $tokenExpr = $this->tokenExpr();

        return [
            'request_count' => (int) (clone $query)->count(),
            'user_count' => (int) (clone $query)->distinct('user_id')->count('user_id'),
            'model_count' => (int) (clone $query)->distinct('model')->count('model'),
            'total_cost' => (string) ((clone $query)->sum('total_cost') ?: '0'),
            'actual_cost' => (string) ((clone $query)->sum('actual_cost') ?: '0'),
            'token_total' => (string) ($tokenExpr === '0' ? 0 : ((clone $query)->sum(DB::raw($tokenExpr)) ?: '0')),
        ];
    }

    public function modelRanking(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $limit): array
    {
        $tokenExpr = $this->tokenExpr();

        return $this->usageQuery($from, $to, $filters)
            ->selectRaw("model, count(*) as request_count, count(distinct user_id) as user_count, sum(total_cost) as total_cost, sum(actual_cost) as actual_cost, sum({$tokenExpr}) as token_total")
            ->groupBy('model')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'model' => $row->model,
                'request_count' => (int) $row->request_count,
                'user_count' => (int) $row->user_count,
                'total_cost' => (string) $row->total_cost,
                'actual_cost' => (string) $row->actual_cost,
                'token_total' => (string) ($row->token_total ?? '0'),
            ])
            ->all();
    }

    public function userTokenRanking(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $tokenExpr = $this->tokenExpr();
        if ($tokenExpr === '0') {
            return [];
        }

        return $this->usageQuery($from, $to, [])
            ->selectRaw("user_id, count(*) as request_count, sum({$tokenExpr}) as token_total, sum(total_cost) as total_cost")
            ->groupBy('user_id')
            ->orderByDesc('token_total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'user_id'       => (int) $row->user_id,
                'request_count' => (int) $row->request_count,
                'token_total'   => (string) ($row->token_total ?? '0'),
                'total_cost'    => (string) ($row->total_cost ?? '0'),
            ])
            ->all();
    }


    public function paymentRechargeTotal(CarbonImmutable $from, CarbonImmutable $to, array $excludeLedgerNos = []): string
    {
        return number_format((float) $this->incomeQuery($from, $to, $excludeLedgerNos)->sum('value'), 2, '.', '');
    }

    public function paymentRechargeRanking(CarbonImmutable $from, CarbonImmutable $to, int $limit, array $excludeLedgerNos = []): array
    {
        return $this->incomeQuery($from, $to, $excludeLedgerNos)
            ->leftJoin('users', 'users.id', '=', 'redeem_codes.used_by')
            ->selectRaw('redeem_codes.used_by as sub2api_user_id, max(users.email) as sub2api_user_email, sum(redeem_codes.value) as total_amount, count(*) as entry_count')
            ->groupBy('redeem_codes.used_by')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'sub2api_user_id' => (int) $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
                'entry_count' => (int) $row->entry_count,
            ])
            ->all();
    }

    private function incomeQuery(CarbonImmutable $from, CarbonImmutable $to, array $excludeLedgerNos = [])
    {
        $query = $this->db()
            ->table('redeem_codes')
            ->whereRaw('LOWER(redeem_codes.type) = ?', ['admin_balance'])
            ->whereRaw('LOWER(redeem_codes.status) = ?', ['used'])
            ->whereNotNull('redeem_codes.used_by')
            ->where('redeem_codes.value', '>', 0)
            ->where('redeem_codes.used_at', '>=', $from->toDateTimeString())
            ->where('redeem_codes.used_at', '<=', $to->toDateTimeString());

        foreach ($excludeLedgerNos as $ledgerNo) {
            $query->where('redeem_codes.notes', 'not like', '%ledger_no='.$ledgerNo.'%');
        }

        return $query;
    }
    public function balanceTotal(): string
    {
        return number_format((float) $this->db()
            ->table('users')
            ->whereNull('deleted_at')
            ->sum('balance'), 2, '.', '');
    }

    public function rechargeSourceSummary(): array
    {
        $payments = $this->db()
            ->table('payment_orders')
            ->whereRaw('LOWER(order_type) = ?', ['balance'])
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->count();

        $redeems = $this->db()
            ->table('redeem_codes')
            ->selectRaw('type, count(*) as count')
            ->whereRaw('LOWER(redeem_codes.status) = ?', ['used'])
            ->whereNotNull('redeem_codes.used_by')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn ($row): array => [
                'type' => $row->type,
                'count' => (int) $row->count,
            ])
            ->all();

        return [
            'payment_orders_completed' => $payments,
            'redeem_codes_used' => $redeems,
        ];
    }

    private function usageQuery(CarbonImmutable $from, CarbonImmutable $to, array $filters)
    {
        $query = $this->db()
            ->table('usage_logs')
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString());

        $model = trim((string) ($filters['model'] ?? ''));
        if ($model !== '') {
            $query->where('model', $model);
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    private function tokenExpr(): string
    {
        $cols = $this->db()->getSchemaBuilder()->getColumnListing('usage_logs');

        foreach (['total_tokens', 'token_total', 'tokens'] as $col) {
            if (in_array($col, $cols, true)) {
                return $col;
            }
        }

        $parts = array_values(array_filter([
            in_array('input_tokens', $cols, true) ? 'input_tokens' : null,
            in_array('output_tokens', $cols, true) ? 'output_tokens' : null,
            in_array('prompt_tokens', $cols, true) ? 'prompt_tokens' : null,
            in_array('completion_tokens', $cols, true) ? 'completion_tokens' : null,
            in_array('cache_creation_tokens', $cols, true) ? 'cache_creation_tokens' : null,
            in_array('cache_read_tokens', $cols, true) ? 'cache_read_tokens' : null,
            in_array('cached_tokens', $cols, true) ? 'cached_tokens' : null,
        ]));

        return count($parts) > 0 ? implode(' + ', $parts) : '0';
    }

    private function userRow(object $row): array
    {
        $item = (array) $row;
        $item['balance'] = number_format((float) $item['balance'], 2, '.', '');
        $item['total_recharged'] = number_format((float) $item['total_recharged'], 2, '.', '');
        $item['created_at'] = ChinaTime::fmt($item['created_at'] ?? null);
        $item['updated_at'] = ChinaTime::fmt($item['updated_at'] ?? null);

        return $item;
    }
}
