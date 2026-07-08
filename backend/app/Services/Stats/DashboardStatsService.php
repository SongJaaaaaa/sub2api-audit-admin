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
        $today = CarbonImmutable::now(config('ledger.timezone', 'Asia/Shanghai'));
        [$todayFrom, $todayTo] = [$today->startOfDay()->utc(), $today->endOfDay()->utc()];

        return [
            'summary' => $summary,
            'today_summary' => $this->repo->usageSummary($todayFrom, $todayTo, []),
            'models' => $this->repo->modelRanking($from, $to, [], min($limit, 12)),
            'cash_total' => $this->cashTotal($from, $to),
            'today_cash_total' => $this->cashTotal($todayFrom, $todayTo),
            'gift_total' => $this->giftTotal($from, $to),
            'today_gift_total' => $this->giftTotal($todayFrom, $todayTo),
            'external_total' => $this->externalTotal($from, $to),
            'recharge_total' => $this->rechargeTotal($from, $to),
            'today_recharge_total' => $this->rechargeTotal($todayFrom, $todayTo),
            'sub2api_balance_total' => $this->repo->balanceTotal(),
            'quota_total' => $this->quotaTotal($from, $to),
            'recharge_rank' => $this->rechargeRank($from, $to, $limit),
            'quota_rank' => $this->quotaRank($from, $to, $limit),
            'user_token_rank' => $this->repo->userTokenRanking($from, $to, $limit),
            'user_cost_rank' => $this->repo->userCostRanking($from, $to, $limit),
            'finance_trend' => $this->financeTrend($from, $to),
            'range' => $this->range($from, $to),
        ];
    }

    public function overview(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'summary' => $this->repo->usageSummary($from, $to, []),
            'models' => [],
            'cash_total' => $this->cashTotal($from, $to),
            'gift_total' => $this->giftTotal($from, $to),
            'external_total' => $this->externalTotal($from, $to),
            'recharge_total' => $this->rechargeTotal($from, $to),
            'quota_total' => $this->quotaTotal($from, $to),
            'recharge_rank' => [],
            'quota_rank' => [],
            'range' => $this->range($from, $to),
        ];
    }

    private function cashTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return number_format((float) $this->ledgerBase($from, $to)
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->sum('cash_amount'), 2, '.', '');
    }

    private function giftTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return number_format((float) $this->ledgerBase($from, $to)
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->sum('gift_quota_amount'), 2, '.', '');
    }

    private function externalTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $this->repo->paymentRechargeTotal($from, $to, $this->auditLedgerNos());
    }

    private function rechargeTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return number_format((float) $this->ledgerBase($from, $to)
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->sum('amount'), 2, '.', '');
    }

    private function quotaTotal(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return number_format((float) $this->ledgerBase($from, $to)->sum('amount'), 2, '.', '');
    }

    private function financeTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');
        $start = $from->setTimezone($tz)->startOfDay();
        $end = $to->setTimezone($tz)->startOfDay();
        $rows = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $rows[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'cash_amount' => '0.00',
                'gift_quota_amount' => '0.00',
                'adjust_total' => '0.00',
            ];
        }

        $this->ledgerBase($from, $to)
            ->get(['operation', 'amount', 'cash_amount', 'gift_quota_amount', 'confirmed_at'])
            ->each(function (LedgerAdjustment $adj) use (&$rows): void {
                $date = substr((string) ChinaTime::fmt($adj->confirmed_at), 0, 10);
                $signed = $adj->operation === LedgerAdjustment::OP_DECREMENT
                    ? -1 * (float) $adj->amount
                    : (float) $adj->amount;

                $this->addTrend($rows, $date, (float) $adj->cash_amount, (float) $adj->gift_quota_amount, $signed);
            });

        return array_values($rows);
    }

    private function addTrend(array &$rows, string $date, float $cash, float $gift, float $adjust): void
    {
        if (! isset($rows[$date])) {
            return;
        }

        $rows[$date]['cash_amount'] = number_format((float) $rows[$date]['cash_amount'] + $cash, 2, '.', '');
        $rows[$date]['gift_quota_amount'] = number_format((float) $rows[$date]['gift_quota_amount'] + $gift, 2, '.', '');
        $rows[$date]['adjust_total'] = number_format((float) $rows[$date]['adjust_total'] + $adjust, 2, '.', '');
    }

    private function rechargeRank(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return $this->ledgerBase($from, $to)
            ->selectRaw('sub2api_user_id, max(sub2api_user_email) as sub2api_user_email, sum(amount) as total_amount, count(*) as entry_count')
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->groupBy('sub2api_user_id')
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
            ->where('confirmed_at', '>=', $from->toDateTimeString())
            ->where('confirmed_at', '<=', $to->toDateTimeString());
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
