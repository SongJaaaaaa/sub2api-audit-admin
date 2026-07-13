<?php

namespace App\Services\BalanceEvents;

use App\Exceptions\LedgerCutoverException;
use App\Models\LedgerAdjustment;
use App\Services\Ledger\LedgerCutoverService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use App\Support\Money;
use App\Support\Sub2ApiNoteTag;

class BalanceEventService
{
    public function __construct(
        private readonly Sub2ApiReadRepository $repo,
        private readonly LedgerCutoverService $cutover,
    ) {}

    public function paginate(ChinaDateRange $range, array $filters, int $page, int $pageSize): array
    {
        $items = $this->items($range, $filters);
        $total = count($items);
        $increment = collect($items)->where('direction', 'increment')->sum(fn (array $row): float => (float) $row['amount']);
        $decrement = collect($items)->where('direction', 'decrement')->sum(fn (array $row): float => (float) $row['amount']);
        $linked = collect($items)->where('link_status', 'linked')->count();
        $summary = [
            'record_count' => $total,
            'user_count' => collect($items)->pluck('sub2api_user_id')->unique()->count(),
            'increment_total' => Money::fmt($increment),
            'decrement_total' => Money::fmt($decrement),
            'net_total' => Money::fmt($increment - $decrement),
            'linked_count' => $linked,
            'external_count' => collect($items)->where('link_status', 'external')->count(),
            'audit_orphan_count' => collect($items)->where('link_status', 'audit_orphan')->count(),
            'linked_rate' => $total > 0 ? round($linked / $total * 100, 2) : 0,
        ];

        return [
            'range' => $this->rangeRow($range),
            'cutover_at' => $this->cutoverAt(),
            'items' => array_slice($items, ($page - 1) * $pageSize, $pageSize),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }

    public function exportRows(ChinaDateRange $range, array $filters): array
    {
        return $this->items($range, $filters);
    }

    public function defaultDates(string $period): array
    {
        $tz = config('ledger.timezone', 'Asia/Shanghai');

        if ($period === 'history') {
            $cutover = $this->cutover->get();
            if (! $cutover) {
                throw new LedgerCutoverException('尚未设置切账时间');
            }

            $end = $cutover->setTimezone($tz)->startOfDay();

            return [$end->subDays(29)->toDateString(), $end->toDateString()];
        }

        $end = now($tz)->toImmutable()->startOfDay();

        return [$end->subDays(29)->toDateString(), $end->toDateString()];
    }

    private function items(ChinaDateRange $range, array $filters): array
    {
        $bounds = $this->periodBounds($range, (string) $filters['period']);
        if (! $bounds) {
            return [];
        }

        [$start, $end] = $bounds;
        $source = (string) ($filters['source'] ?? '');
        $rows = [];

        if ($source === '' || $source === 'admin_adjustment') {
            array_push($rows, ...$this->repo->adminAdjustmentEvents($start, $end));
        }
        if ($source === '' || $source === 'balance_redeem') {
            array_push($rows, ...$this->repo->balanceRedeemEvents($start, $end));
        }
        if ($source === '' || $source === 'payment_order') {
            array_push($rows, ...$this->repo->paymentOrderEvents($start, $end));
        }

        $rows = $this->linkRows($rows);
        $userId = (int) ($filters['user_id'] ?? 0);
        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $direction = (string) ($filters['direction'] ?? '');
        $linkStatus = (string) ($filters['link_status'] ?? '');

        return collect($rows)
            ->filter(function (array $row) use ($userId, $keyword, $direction, $linkStatus): bool {
                if ($userId > 0 && $row['sub2api_user_id'] !== $userId) {
                    return false;
                }
                if ($direction !== '' && $row['direction'] !== $direction) {
                    return false;
                }
                if ($linkStatus !== '' && $row['link_status'] !== $linkStatus) {
                    return false;
                }
                if ($keyword !== '') {
                    $text = mb_strtolower(implode(' ', [
                        $row['sub2api_user_id'],
                        $row['user_email'],
                        $row['username'],
                    ]));

                    if (! str_contains($text, $keyword)) {
                        return false;
                    }
                }

                return true;
            })
            ->sortByDesc(fn (array $row): string => $row['event_at'].'-'.str_pad((string) $row['remote_event_id'], 20, '0', STR_PAD_LEFT))
            ->values()
            ->all();
    }

    private function linkRows(array $rows): array
    {
        $adminRows = collect($rows)->where('source', 'admin_adjustment');
        $sourceIds = $adminRows->pluck('remote_event_id')->filter()->unique()->values()->all();
        $keys = $adminRows
            ->map(fn (array $row): ?string => Sub2ApiNoteTag::idempotencyKey($row['notes'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $adjs = ($sourceIds === [] && $keys === [])
            ? collect()
            : LedgerAdjustment::query()
                ->where(function ($query) use ($sourceIds, $keys): void {
                    if ($sourceIds !== []) {
                        $query->whereIn('sub2api_source_id', $sourceIds);
                    }
                    if ($keys !== []) {
                        $method = $sourceIds === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('idempotency_key', $keys);
                    }
                })
                ->get();
        $bySource = $adjs->whereNotNull('sub2api_source_id')->keyBy('sub2api_source_id');
        $byPair = $adjs->whereNull('sub2api_source_id')
            ->keyBy(fn (LedgerAdjustment $adj): string => $adj->sub2api_user_id.':'.$adj->idempotency_key);

        return collect($rows)->map(function (array $row) use ($bySource, $byPair): array {
            $value = (float) $row['value'];
            $adj = null;
            $tag = Sub2ApiNoteTag::parse($row['notes'] ?? null);

            if ($row['source'] === 'admin_adjustment') {
                $adj = $bySource->get($row['remote_event_id']);
                if (! $adj && $tag['idempotency_key']) {
                    $adj = $byPair->get($row['sub2api_user_id'].':'.$tag['idempotency_key']);
                }
            }

            $linkStatus = $adj
                ? 'linked'
                : (($row['source'] === 'admin_adjustment' && $tag['is_audit']) ? 'audit_orphan' : 'external');

            return [
                'event_at' => ChinaTime::fmtUtc($row['event_at']),
                'source' => $row['source'],
                'remote_event_id' => (int) $row['remote_event_id'],
                'sub2api_user_id' => (int) $row['sub2api_user_id'],
                'user_email' => $row['user_email'] ?: null,
                'username' => $row['username'] ?: null,
                'direction' => $value < 0 ? 'decrement' : 'increment',
                'amount' => $this->decimal(abs($value), 8),
                'notes' => $row['notes'] ?: null,
                'link_status' => $linkStatus,
                'ledger_adjustment_id' => $adj?->id,
                'ledger_no' => $adj?->ledger_no,
            ];
        })->all();
    }

    private function periodBounds(ChinaDateRange $range, string $period): ?array
    {
        if ($period === 'all') {
            return [$range->utcStart, $range->utcEndExclusive];
        }

        $cutover = $this->cutover->get();
        if (! $cutover) {
            throw new LedgerCutoverException('尚未设置切账时间');
        }

        $start = $range->utcStart;
        $end = $range->utcEndExclusive;

        if ($period === 'history') {
            $end = $end->lessThan($cutover) ? $end : $cutover;
        } else {
            $start = $start->greaterThan($cutover) ? $start : $cutover;
        }

        return $start->lessThan($end) ? [$start, $end] : null;
    }

    private function rangeRow(ChinaDateRange $range): array
    {
        return [
            'start_date' => $range->startDate,
            'end_date' => $range->endDate,
            'timezone' => $range->timezone,
        ];
    }

    private function cutoverAt(): ?string
    {
        $cutover = $this->cutover->get();

        return $cutover ? ChinaTime::fmtUtc($cutover) : null;
    }

    private function decimal(mixed $value, int $scale): string
    {
        $text = number_format((float) $value, $scale, '.', '');

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }
}
