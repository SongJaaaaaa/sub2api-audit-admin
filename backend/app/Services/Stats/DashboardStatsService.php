<?php

namespace App\Services\Stats;

use App\Models\LedgerAdjustment;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaTime;
use Carbon\CarbonImmutable;

class DashboardStatsService
{
    public function __construct(private readonly Sub2ApiReadRepository $repo) {}

    public function data(CarbonImmutable $from, CarbonImmutable $to, string $group, int $limit): array
    {
        $summary = $this->repo->usageSummary($from, $to, []);
        $models = $this->repo->modelRanking($from, $to, [], $limit);
        if ($group === 'gpt') {
            $models = array_values(array_filter($models, fn (array $row): bool => preg_match('/gpt|chatgpt|o[1-9]/i', $row['model']) === 1));
        } elseif ($group === 'claude') {
            $models = array_values(array_filter($models, fn (array $row): bool => stripos($row['model'], 'claude') !== false));
        }

        return [
            'summary' => $summary,
            'models' => array_slice($models, 0, $limit),
            'recharge_total' => $this->rechargeTotal($from, $to),
            'sub2api_balance_total' => $this->repo->balanceTotal(),
            'quota_total' => $this->quotaTotal($from, $to),
            'recharge_rank' => $this->rechargeRank($from, $to, $limit),
            'quota_rank' => $this->quotaRank($from, $to, $limit),
            'user_token_rank' => $this->repo->userTokenRanking($from, $to, $limit),
            'range' => $this->range($from, $to),
        ];
    }

    public function overview(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'summary' => $this->repo->usageSummary($from, $to, []),
            'models' => [],
            'recharge_total' => $this->rechargeTotal($from, $to),
            'quota_total' => $this->quotaTotal($from, $to),
            'recharge_rank' => [],
            'quota_rank' => [],
            'range' => $this->range($from, $to),
        ];
    }

    private function rechargeTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $auditTotal = (float) $this->ledgerBase($from, $to)
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->sum('amount');

        return number_format($auditTotal + (float) $this->repo->paymentRechargeTotal($from, $to, $this->auditLedgerNos()), 2, '.', '');
    }

    private function quotaTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return number_format((float) $this->ledgerBase($from, $to)->sum('amount'), 2, '.', '');
    }

    private function rechargeRank(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $rows = [];
        $auditRows = $this->ledgerBase($from, $to)
            ->selectRaw('sub2api_user_id, max(sub2api_user_email) as sub2api_user_email, sum(amount) as total_amount, count(*) as entry_count')
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->groupBy('sub2api_user_id')
            ->get()
            ->map(fn ($row): array => [
                'sub2api_user_id' => (int) $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
                'entry_count' => (int) $row->entry_count,
            ])
            ->all();

        foreach (array_merge($auditRows, $this->repo->paymentRechargeRanking($from, $to, $limit, $this->auditLedgerNos())) as $row) {
            $key = (string) $row['sub2api_user_id'];
            if (! isset($rows[$key])) {
                $rows[$key] = $row;
                continue;
            }

            if (! $rows[$key]['sub2api_user_email'] && $row['sub2api_user_email']) {
                $rows[$key]['sub2api_user_email'] = $row['sub2api_user_email'];
            }
            $rows[$key]['total_amount'] = number_format((float) $rows[$key]['total_amount'] + (float) $row['total_amount'], 2, '.', '');
            $rows[$key]['entry_count'] += (int) $row['entry_count'];
        }

        usort($rows, fn (array $a, array $b): int => (float) $b['total_amount'] <=> (float) $a['total_amount']);

        return array_slice($rows, 0, $limit);
    }

    private function quotaRank(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return $this->ledgerBase($from, $to)
            ->selectRaw("sub2api_user_id, max(sub2api_user_email) as sub2api_user_email, sum(case when operation = 'decrement' then -amount else amount end) as total_amount, count(*) as entry_count")
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

    private function ledgerBase(CarbonImmutable $from, CarbonImmutable $to)
    {
        return LedgerAdjustment::query()
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->where('confirmed_at', '>=', ChinaTime::utcText($from))
            ->where('confirmed_at', '<=', ChinaTime::utcText($to));
    }

    private function auditLedgerNos(): array
    {
        return LedgerAdjustment::query()
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->pluck('ledger_no')
            ->filter()
            ->values()
            ->all();
    }

    private function range(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'from' => ChinaTime::fmt($from),
            'to' => ChinaTime::fmt($to),
        ];
    }
}
