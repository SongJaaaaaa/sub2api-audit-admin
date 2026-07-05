<?php

namespace App\Services\Stats;

use App\Models\CashEntry;
use App\Models\LedgerAdjustment;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use Carbon\CarbonImmutable;

class DashboardStatsService
{
    public function __construct(private readonly Sub2ApiReadRepository $repo) {}

    public function data(CarbonImmutable $from, CarbonImmutable $to, string $group, int $limit): array
    {
        $models = $this->repo->modelRanking($from, $to, [], $limit);
        if ($group === 'gpt') {
            $models = array_values(array_filter($models, fn (array $row): bool => preg_match('/gpt|chatgpt|o[1-9]/i', $row['model']) === 1));
        } elseif ($group === 'claude') {
            $models = array_values(array_filter($models, fn (array $row): bool => stripos($row['model'], 'claude') !== false));
        }

        return [
            'summary' => $this->repo->usageSummary($from, $to, []),
            'models' => array_slice($models, 0, $limit),
            'recharge_rank' => $this->rechargeRank($from, $to, $limit),
            'quota_rank' => $this->quotaRank($from, $to, $limit),
            'range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
        ];
    }

    private function rechargeRank(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return CashEntry::query()
            ->selectRaw('sub2api_user_id, max(sub2api_user_email) as sub2api_user_email, sum(cash_amount) as total_amount, count(*) as entry_count')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->groupBy('sub2api_user_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'sub2api_user_id' => $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
                'entry_count' => (int) $row->entry_count,
            ])->all();
    }

    private function quotaRank(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return LedgerAdjustment::query()
            ->selectRaw("sub2api_user_id, max(sub2api_user_email) as sub2api_user_email, sum(case when operation = 'decrement' then -amount else amount end) as total_amount, count(*) as entry_count")
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->where('confirmed_at', '>=', $from)
            ->where('confirmed_at', '<=', $to)
            ->groupBy('sub2api_user_id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'sub2api_user_id' => $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
                'entry_count' => (int) $row->entry_count,
            ])->all();
    }
}
