<?php

namespace App\Services\Sub2Api;

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

        return [
            'request_count' => (int) (clone $query)->count(),
            'user_count' => (int) (clone $query)->distinct('user_id')->count('user_id'),
            'model_count' => (int) (clone $query)->distinct('model')->count('model'),
            'total_cost' => (string) ((clone $query)->sum('total_cost') ?: '0'),
            'actual_cost' => (string) ((clone $query)->sum('actual_cost') ?: '0'),
        ];
    }

    public function modelRanking(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $limit): array
    {
        return $this->usageQuery($from, $to, $filters)
            ->selectRaw('model, count(*) as request_count, count(distinct user_id) as user_count, sum(total_cost) as total_cost, sum(actual_cost) as actual_cost')
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
            ])
            ->all();
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
            ->whereRaw('LOWER(status) = ?', ['used'])
            ->whereNotNull('used_by')
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

    private function userRow(object $row): array
    {
        $item = (array) $row;
        $item['balance'] = number_format((float) $item['balance'], 2, '.', '');
        $item['total_recharged'] = number_format((float) $item['total_recharged'], 2, '.', '');

        return $item;
    }
}
