<?php

namespace App\Services\Stats;

use App\Models\LedgerAdjustment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationDiff;
use App\Services\Ledger\LedgerCutoverService;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use Illuminate\Database\Eloquent\Builder;

class DashboardStatsService
{
    public function __construct(
        private readonly Sub2ApiAdminClient $client,
        private readonly Sub2ApiReadRepository $repo,
        private readonly LedgerCutoverService $cutover,
    ) {}

    public function data(ChinaDateRange $range, int $limit): array
    {
        $trend = $this->usageTrend($range, $this->client->dashboardTrend($range)['trend']);
        $models = $this->modelRanking($this->client->dashboardModels($range)['models'], $limit);
        $costUsers = $this->userCostRanking($this->client->dashboardUsersRanking($range, $limit)['ranking'], $limit);
        $tokenUsers = $this->userTokenRanking($this->client->dashboardUserBreakdown($range, $limit)['users'], $limit);
        $finance = $this->finance($range);
        $cutover = $this->cutover->get();

        return [
            'range' => [
                'start_date' => $range->startDate,
                'end_date' => $range->endDate,
                'timezone' => $range->timezone,
            ],
            'cutover_at' => $cutover ? ChinaTime::fmtUtc($cutover) : null,
            'finance' => $finance,
            'usage' => [
                ...$this->usageSummary($trend),
                'trend' => $trend,
            ],
            'balance' => $this->repo->activeUserBalanceSnapshot(),
            'rankings' => [
                'recharge_users' => $this->rechargeRanking($range, $limit),
                'user_tokens' => $tokenUsers,
                'user_actual_cost' => $costUsers,
                'models' => $models,
            ],
            'recent_adjustments' => $this->recentAdjustments($range, $limit),
            'alerts' => $this->alerts($range),
        ];
    }

    private function finance(ChinaDateRange $range): array
    {
        $rows = $this->ledgerBase($range)
            ?->get(['operation', 'amount', 'cash_amount', 'gift_quota_amount', 'confirmed_at'])
            ?? collect();
        $in = 0.0;
        $out = 0.0;
        $cash = 0.0;
        $gift = 0.0;
        $trend = [];

        foreach ($range->dates() as $date) {
            $trend[$date] = [
                'date' => $date,
                'cash_total' => '0.00',
                'gift_total' => '0.00',
                'adjustment_in_total' => '0.00',
                'adjustment_out_total' => '0.00',
                'adjustment_net_total' => '0.00',
            ];
        }

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            $isIn = $row->operation === LedgerAdjustment::OP_INCREMENT;
            $date = substr((string) ChinaTime::fmt($row->confirmed_at), 0, 10);

            if ($isIn) {
                $in += $amount;
                $cash += (float) $row->cash_amount;
                $gift += (float) $row->gift_quota_amount;
            } else {
                $out += abs($amount);
            }

            if (! isset($trend[$date])) {
                continue;
            }

            $item = &$trend[$date];
            if ($isIn) {
                $item['cash_total'] = $this->money((float) $item['cash_total'] + (float) $row->cash_amount);
                $item['gift_total'] = $this->money((float) $item['gift_total'] + (float) $row->gift_quota_amount);
                $item['adjustment_in_total'] = $this->money((float) $item['adjustment_in_total'] + $amount);
            } else {
                $item['adjustment_out_total'] = $this->money((float) $item['adjustment_out_total'] + abs($amount));
            }
            $item['adjustment_net_total'] = $this->money(
                (float) $item['adjustment_in_total'] - (float) $item['adjustment_out_total'],
            );
            unset($item);
        }

        return [
            'cash_total' => $this->money($cash),
            'gift_total' => $this->money($gift),
            'adjustment_in_total' => $this->money($in),
            'adjustment_out_total' => $this->money($out),
            'adjustment_net_total' => $this->money($in - $out),
            'trend' => array_values($trend),
        ];
    }

    private function usageTrend(ChinaDateRange $range, array $rows): array
    {
        $trend = [];
        foreach ($range->dates() as $date) {
            $trend[$date] = [
                'date' => $date,
                'request_count' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cache_creation_tokens' => 0,
                'cache_read_tokens' => 0,
                'total_tokens' => 0,
                'standard_cost' => '0',
                'actual_cost' => '0',
            ];
        }

        foreach ($rows as $row) {
            $date = (string) ($row['date'] ?? '');
            if (! isset($trend[$date])) {
                continue;
            }

            $trend[$date] = [
                'date' => $date,
                'request_count' => (int) $row['requests'],
                'input_tokens' => (int) $row['input_tokens'],
                'output_tokens' => (int) $row['output_tokens'],
                'cache_creation_tokens' => (int) $row['cache_creation_tokens'],
                'cache_read_tokens' => (int) $row['cache_read_tokens'],
                'total_tokens' => (int) $row['total_tokens'],
                'standard_cost' => $this->decimal($row['cost'], 10),
                'actual_cost' => $this->decimal($row['actual_cost'], 10),
            ];
        }

        return array_values($trend);
    }

    private function usageSummary(array $trend): array
    {
        $requests = 0;
        $tokens = 0;
        $standard = 0.0;
        $actual = 0.0;

        foreach ($trend as $row) {
            $requests += $row['request_count'];
            $tokens += $row['total_tokens'];
            $standard += (float) $row['standard_cost'];
            $actual += (float) $row['actual_cost'];
        }

        return [
            'request_count' => $requests,
            'total_tokens' => $tokens,
            'standard_cost' => $this->decimal($standard, 10),
            'actual_cost' => $this->decimal($actual, 10),
        ];
    }

    private function rechargeRanking(ChinaDateRange $range, int $limit): array
    {
        $query = $this->ledgerBase($range);
        if (! $query) {
            return [];
        }

        return $query
            ->where('operation', LedgerAdjustment::OP_INCREMENT)
            ->where('cash_amount', '>', 0)
            ->selectRaw('sub2api_user_id, max(sub2api_user_email) as email, sum(cash_amount) as cash_total, count(*) as entry_count')
            ->groupBy('sub2api_user_id')
            ->orderByDesc('cash_total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'user_id' => (int) $row->sub2api_user_id,
                'email' => $row->email,
                'cash_total' => $this->money($row->cash_total),
                'entry_count' => (int) $row->entry_count,
            ])
            ->all();
    }

    private function userTokenRanking(array $rows, int $limit): array
    {
        return collect($rows)
            ->sortByDesc(fn (array $row): int => (int) $row['total_tokens'])
            ->take($limit)
            ->values()
            ->map(fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'email' => $row['email'] ?? null,
                'request_count' => (int) $row['requests'],
                'input_tokens' => (int) $row['input_tokens'],
                'output_tokens' => (int) $row['output_tokens'],
                'cache_tokens' => (int) $row['cache_tokens'],
                'total_tokens' => (int) $row['total_tokens'],
                'standard_cost' => $this->decimal($row['cost'], 10),
                'actual_cost' => $this->decimal($row['actual_cost'], 10),
            ])
            ->all();
    }

    private function userCostRanking(array $rows, int $limit): array
    {
        return collect($rows)
            ->sortByDesc(fn (array $row): float => (float) $row['actual_cost'])
            ->take($limit)
            ->values()
            ->map(fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'email' => $row['email'] ?? null,
                'actual_cost' => $this->decimal($row['actual_cost'], 10),
                'request_count' => (int) $row['requests'],
                'total_tokens' => (int) $row['tokens'],
            ])
            ->all();
    }

    private function modelRanking(array $rows, int $limit): array
    {
        return collect($rows)
            ->sortByDesc(fn (array $row): int => (int) $row['total_tokens'])
            ->take($limit)
            ->values()
            ->map(fn (array $row): array => [
                'model' => (string) $row['model'],
                'request_count' => (int) $row['requests'],
                'input_tokens' => (int) $row['input_tokens'],
                'output_tokens' => (int) $row['output_tokens'],
                'cache_creation_tokens' => (int) $row['cache_creation_tokens'],
                'cache_read_tokens' => (int) $row['cache_read_tokens'],
                'total_tokens' => (int) $row['total_tokens'],
                'standard_cost' => $this->decimal($row['cost'], 10),
                'actual_cost' => $this->decimal($row['actual_cost'], 10),
            ])
            ->all();
    }

    private function recentAdjustments(ChinaDateRange $range, int $limit): array
    {
        $bounds = $this->cutover->ledgerLocalBounds($range);
        if (! $bounds) {
            return [];
        }

        [$start, $end] = $bounds;

        return LedgerAdjustment::query()
            ->whereIn('status', [
                LedgerAdjustment::STATUS_SUCCEEDED,
                LedgerAdjustment::STATUS_EXCEPTION,
                LedgerAdjustment::STATUS_VOIDED,
            ])
            ->whereRaw('COALESCE(confirmed_at, created_at) >= ?', [$start->format(ChinaTime::FORMAT)])
            ->whereRaw('COALESCE(confirmed_at, created_at) < ?', [$end->format(ChinaTime::FORMAT)])
            ->orderByRaw('COALESCE(confirmed_at, created_at) DESC')
            ->limit($limit)
            ->get()
            ->map(fn (LedgerAdjustment $row): array => [
                'id' => $row->id,
                'ledger_no' => $row->ledger_no,
                'sub2api_source_id' => $row->sub2api_source_id,
                'sub2api_user_id' => $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'operation' => $row->operation,
                'amount' => $row->amount,
                'cash_amount' => $row->cash_amount,
                'gift_quota_amount' => $row->gift_quota_amount,
                'status' => $row->status,
                'adjust_reason' => $row->adjust_reason,
                'exception_reason' => $row->exception_reason,
                'event_at' => ChinaTime::fmt($row->confirmed_at ?? $row->created_at),
            ])
            ->all();
    }

    private function alerts(ChinaDateRange $range): array
    {
        $unlinked = $this->ledgerBase($range)?->whereNull('sub2api_source_id')->count() ?? 0;
        $diffs = ReconciliationDiff::query()
            ->join('reconciliation_batches as rb', 'rb.id', '=', 'reconciliation_diffs.reconciliation_batch_id')
            ->where('rb.biz_date', '>=', $range->startDate)
            ->where('rb.biz_date', '<=', $range->endDate)
            ->get([
                'reconciliation_diffs.id',
                'reconciliation_diffs.type',
                'reconciliation_diffs.local_adjustment_id',
                'reconciliation_diffs.remote_event_id',
            ]);

        $issues = $diffs->whereIn('type', [
            'local_missing_remote',
            'user_mismatch',
            'direction_mismatch',
            'amount_mismatch',
            'duplicate_source_link',
        ]);
        $issueKeys = $issues->map(fn ($row): string => $row->local_adjustment_id
            ? 'local:'.$row->local_adjustment_id
            : ($row->remote_event_id ? 'remote:'.$row->remote_event_id : 'diff:'.$row->id));
        $external = $diffs->where('type', 'remote_external')->pluck('remote_event_id')->filter()->unique()->count();
        $orphans = $diffs->where('type', 'remote_audit_orphan')->pluck('remote_event_id')->filter()->unique()->count();
        $last = ReconciliationBatch::query()->max('biz_date');

        return [
            'unlinked_adjustment_count' => (int) $unlinked,
            'reconcile_issue_count' => $issueKeys->unique()->count(),
            'external_adjustment_count' => $external,
            'audit_orphan_count' => $orphans,
            'last_reconciled_date' => $last ? substr((string) $last, 0, 10) : null,
        ];
    }

    private function ledgerBase(ChinaDateRange $range): ?Builder
    {
        $bounds = $this->cutover->ledgerLocalBounds($range);
        if (! $bounds) {
            return null;
        }

        [$start, $end] = $bounds;

        return LedgerAdjustment::query()
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->where('confirmed_at', '>=', $start->format(ChinaTime::FORMAT))
            ->where('confirmed_at', '<', $end->format(ChinaTime::FORMAT));
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function decimal(mixed $value, int $scale): string
    {
        $text = number_format((float) $value, $scale, '.', '');

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }
}
