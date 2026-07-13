<?php

namespace App\Services\Reconcile;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationDiff;
use App\Services\Audit\AuditLogService;
use App\Services\Ledger\LedgerCutoverService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaTime;
use App\Support\Sub2ApiNoteTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileService
{
    public function __construct(
        private readonly LedgerCutoverService $cutover,
        private readonly Sub2ApiReadRepository $repo,
        private readonly AuditLogService $audit,
    ) {}

    public function create(?Admin $admin, string $date): ReconciliationBatch
    {
        $ranges = $this->cutover->reconcileRanges($date);

        [$batch, $beforeRow] = DB::transaction(function () use ($admin, $date, $ranges): array {
            $batch = ReconciliationBatch::query()->whereDate('biz_date', $date)->lockForUpdate()->first();
            $beforeRow = $batch ? $this->row($batch) : null;
            $local = LedgerAdjustment::query()
                ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
                ->where('confirmed_at', '>=', $ranges['local_start']->format(ChinaTime::FORMAT))
                ->where('confirmed_at', '<', $ranges['local_end']->format(ChinaTime::FORMAT))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $remote = $this->repo->adminAdjustmentEvents($ranges['utc_start'], $ranges['utc_end']);
            $result = $this->compare($local->all(), $remote);
            $status = $this->status($result['diffs']);
            $localNet = $this->sum($local->map(fn (LedgerAdjustment $adj): float => $this->localSigned($adj))->all());
            $cash = $this->sum($local
                ->where('operation', LedgerAdjustment::OP_INCREMENT)
                ->pluck('cash_amount')
                ->map(fn ($value): float => (float) $value)
                ->all());
            $gift = $this->sum($local
                ->where('operation', LedgerAdjustment::OP_INCREMENT)
                ->pluck('gift_quota_amount')
                ->map(fn ($value): float => (float) $value)
                ->all());
            $values = [
                'batch_no' => $batch?->batch_no ?? $this->batchNo(),
                'biz_date' => $date,
                'period_start' => $ranges['local_start']->format(ChinaTime::FORMAT),
                'period_end' => $ranges['local_end']->format(ChinaTime::FORMAT),
                'cash_total' => $this->decimal($cash, 2),
                'quota_total' => $this->decimal($localNet, 2),
                'gift_total' => $this->decimal($gift, 2),
                'sub2api_delta_total' => $this->decimal($result['matched_net'], 2),
                'diff_amount' => $this->decimal($localNet - $result['matched_net'], 2),
                'local_success_count' => $local->count(),
                'local_adjustment_net' => $this->decimal($localNet, 8),
                'remote_matched_count' => count($result['matched']),
                'remote_matched_net' => $this->decimal($result['matched_net'], 8),
                'external_count' => count($result['external']),
                'external_net' => $this->decimal($result['external_net'], 8),
                'audit_orphan_count' => count($result['orphans']),
                'audit_orphan_net' => $this->decimal($result['orphan_net'], 8),
                'issue_count' => collect($result['diffs'])
                    ->whereIn('type', [
                        'local_missing_remote',
                        'user_mismatch',
                        'direction_mismatch',
                        'amount_mismatch',
                        'duplicate_source_link',
                    ])
                    ->count(),
                'status' => $status,
                'created_by' => $admin?->id ?? $batch?->created_by,
            ];

            if ($batch) {
                $batch->update($values);
                $batch->diffs()->delete();
            } else {
                $batch = ReconciliationBatch::query()->create($values);
            }

            foreach ($result['diffs'] as $diff) {
                $batch->diffs()->create($diff);
            }

            return [$batch->refresh(), $beforeRow];
        });

        if ($admin) {
            $this->audit->record(
                $admin,
                'reconcile.run',
                'reconciliation_batch',
                $batch->id,
                $beforeRow,
                $this->row($batch),
            );
        }

        return $batch;
    }

    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = ReconciliationBatch::query();
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $query->whereDate('biz_date', '>=', $startDate);
        }
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $query->whereDate('biz_date', '<=', $endDate);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
        if (($filters['has_external'] ?? '') !== '') {
            $query->where('external_count', ($filters['has_external'] === '1') ? '>' : '=', 0);
        }
        if (($filters['has_orphan'] ?? '') !== '') {
            $query->where('audit_orphan_count', ($filters['has_orphan'] === '1') ? '>' : '=', 0);
        }
        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $query->where('created_by', $createdBy);
        }

        $total = (clone $query)->count();
        $ok = (clone $query)->where('status', ReconciliationBatch::STATUS_OK)->count();
        $latestBizDate = (clone $query)->max('biz_date');
        $lastSuccess = (clone $query)->where('status', ReconciliationBatch::STATUS_OK)->max('biz_date');
        $batchIds = (clone $query)->pluck('id');
        $moneyTypes = ['local_missing_remote', 'remote_external', 'remote_audit_orphan', 'amount_mismatch'];
        $summary = [
            'batch_count' => $total,
            'ok_count' => $ok,
            'warning_count' => (clone $query)->where('status', ReconciliationBatch::STATUS_WARNING)->count(),
            'error_count' => (clone $query)->where('status', ReconciliationBatch::STATUS_ERROR)->count(),
            'diff_count' => ReconciliationDiff::query()->whereIn('reconciliation_batch_id', $batchIds)->count(),
            'diff_amount' => $this->decimal(ReconciliationDiff::query()
                ->whereIn('reconciliation_batch_id', $batchIds)
                ->whereIn('type', $moneyTypes)
                ->sum('amount'), 2),
            'healthy_rate' => $total > 0 ? round($ok / $total * 100, 2) : 0,
            'last_success_date' => $lastSuccess,
            'unreconciled_days' => $latestBizDate
                ? max(now(config('ledger.timezone', 'Asia/Shanghai'))->subDay()->startOfDay()->diffInDays($latestBizDate, false) * -1, 0)
                : null,
        ];
        $items = $query->orderByDesc('biz_date')->forPage($page, $pageSize)->get()
            ->map(fn (ReconciliationBatch $row): array => $this->row($row))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }

    public function diffs(ReconciliationBatch $batch): array
    {
        return $batch->diffs()
            ->orderBy('id')
            ->get()
            ->map(fn (ReconciliationDiff $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'title' => $row->title,
                'amount' => $row->amount,
                'reason' => $row->reason,
                'local_adjustment_id' => $row->local_adjustment_id,
                'remote_event_id' => $row->remote_event_id,
                'sub2api_user_id' => $row->sub2api_user_id,
                'direction' => $row->direction,
                'local_amount' => $row->local_amount,
                'remote_amount' => $row->remote_amount,
            ])
            ->all();
    }

    public function row(ReconciliationBatch $row): array
    {
        return [
            'id' => $row->id,
            'batch_no' => $row->batch_no,
            'biz_date' => $row->biz_date?->toDateString(),
            'period_start' => ChinaTime::fmt($row->period_start),
            'period_end' => ChinaTime::fmt($row->period_end),
            'cash_total' => $row->cash_total,
            'quota_total' => $row->quota_total,
            'gift_total' => $row->gift_total,
            'sub2api_delta_total' => $row->sub2api_delta_total,
            'diff_amount' => $row->diff_amount,
            'local_success_count' => $row->local_success_count,
            'local_adjustment_net' => $row->local_adjustment_net,
            'remote_matched_count' => $row->remote_matched_count,
            'remote_matched_net' => $row->remote_matched_net,
            'external_count' => $row->external_count,
            'external_net' => $row->external_net,
            'audit_orphan_count' => $row->audit_orphan_count,
            'audit_orphan_net' => $row->audit_orphan_net,
            'issue_count' => $row->issue_count,
            'status' => $row->status,
            'created_at' => ChinaTime::fmt($row->created_at),
            'updated_at' => ChinaTime::fmt($row->updated_at),
        ];
    }

    private function compare(array $local, array $remote): array
    {
        $remote = collect($remote)->map(function (array $row): array {
            $row['tag'] = Sub2ApiNoteTag::parse($row['notes'] ?? null);

            return $row;
        });
        $byId = $remote->keyBy('remote_event_id');
        $byPair = $remote
            ->filter(fn (array $row): bool => (bool) $row['tag']['idempotency_key'])
            ->groupBy(fn (array $row): string => $row['sub2api_user_id'].':'.$row['tag']['idempotency_key']);
        $claimed = [];
        $matched = [];
        $diffs = [];

        foreach ($local as $adj) {
            $candidates = $adj->sub2api_source_id
                ? collect([$byId->get((int) $adj->sub2api_source_id)])->filter()->values()
                : collect($byPair->get($adj->sub2api_user_id.':'.$adj->idempotency_key, []))->values();

            if ($candidates->count() === 0) {
                $diffs[] = $this->diff('local_missing_remote', $adj, null, '未找到对应的 Sub2API 后台调额事件');

                continue;
            }

            if ($candidates->count() > 1) {
                foreach ($candidates as $event) {
                    $claimed[(int) $event['remote_event_id']] = $adj->id;
                }
                $diffs[] = $this->diff('duplicate_source_link', $adj, null, '完整幂等键匹配到多个远端事件');

                continue;
            }

            $event = $candidates->first();
            $eventId = (int) $event['remote_event_id'];
            if (isset($claimed[$eventId]) && $claimed[$eventId] !== $adj->id) {
                $diffs[] = $this->diff('duplicate_source_link', $adj, $event, '同一远端事件被多个本地成功单关联');

                continue;
            }

            $claimed[$eventId] = $adj->id;
            $matched[$eventId] = $event;

            if ((int) $event['sub2api_user_id'] !== (int) $adj->sub2api_user_id) {
                $diffs[] = $this->diff('user_mismatch', $adj, $event, '本地用户与远端事件用户不一致');
            }

            if ($this->remoteDirection($event) !== $adj->operation) {
                $diffs[] = $this->diff('direction_mismatch', $adj, $event, '本地调额方向与远端事件方向不一致');
            }

            if ($this->decimal(abs((float) $event['value']), 8) !== $this->decimal(abs((float) $adj->amount), 8)) {
                $diffs[] = $this->diff('amount_mismatch', $adj, $event, '本地调额金额与远端事件金额不一致');
            }
        }

        $external = [];
        $orphans = [];
        foreach ($remote as $event) {
            $eventId = (int) $event['remote_event_id'];
            if (isset($claimed[$eventId])) {
                continue;
            }

            if ($event['tag']['is_audit']) {
                $orphans[$eventId] = $event;
                $diffs[] = $this->diff('remote_audit_orphan', null, $event, '远端事件带审计标记，但未找到对应的本地成功单');
            } else {
                $external[$eventId] = $event;
                $diffs[] = $this->diff('remote_external', null, $event, '远端后台调额不是由本审计系统产生');
            }
        }

        return [
            'matched' => $matched,
            'matched_net' => $this->sum(array_map(fn (array $row): float => (float) $row['value'], $matched)),
            'external' => $external,
            'external_net' => $this->sum(array_map(fn (array $row): float => (float) $row['value'], $external)),
            'orphans' => $orphans,
            'orphan_net' => $this->sum(array_map(fn (array $row): float => (float) $row['value'], $orphans)),
            'diffs' => $diffs,
        ];
    }

    private function diff(string $type, ?LedgerAdjustment $adj, ?array $event, string $reason): array
    {
        $localAmount = $adj ? abs((float) $adj->amount) : null;
        $remoteAmount = $event ? abs((float) $event['value']) : null;
        $amount = match ($type) {
            'amount_mismatch' => abs((float) $localAmount - (float) $remoteAmount),
            default => $localAmount ?? $remoteAmount ?? 0,
        };

        return [
            'type' => $type,
            'title' => $this->title($type),
            'amount' => $this->decimal($amount, 2),
            'reason' => $reason,
            'local_adjustment_id' => $adj?->id,
            'remote_event_id' => $event['remote_event_id'] ?? null,
            'sub2api_user_id' => $adj?->sub2api_user_id ?? ($event['sub2api_user_id'] ?? null),
            'direction' => $adj?->operation ?? ($event ? $this->remoteDirection($event) : null),
            'local_amount' => $localAmount === null ? null : $this->decimal($localAmount, 8),
            'remote_amount' => $remoteAmount === null ? null : $this->decimal($remoteAmount, 8),
        ];
    }

    private function status(array $diffs): string
    {
        if ($diffs === []) {
            return ReconciliationBatch::STATUS_OK;
        }

        $warningTypes = ['remote_external', 'remote_audit_orphan'];
        $hasError = collect($diffs)->contains(fn (array $diff): bool => ! in_array($diff['type'], $warningTypes, true));

        return $hasError ? ReconciliationBatch::STATUS_ERROR : ReconciliationBatch::STATUS_WARNING;
    }

    private function title(string $type): string
    {
        return match ($type) {
            'local_missing_remote' => '本地成功单缺少远端事件',
            'remote_external' => '外部后台调额',
            'remote_audit_orphan' => '审计孤儿',
            'user_mismatch' => '用户不一致',
            'direction_mismatch' => '方向不一致',
            'amount_mismatch' => '金额不一致',
            'duplicate_source_link' => '远端事件重复关联',
        };
    }

    private function localSigned(LedgerAdjustment $adj): float
    {
        $amount = abs((float) $adj->amount);

        return $adj->operation === LedgerAdjustment::OP_DECREMENT ? -$amount : $amount;
    }

    private function remoteDirection(array $event): string
    {
        return (float) $event['value'] < 0
            ? LedgerAdjustment::OP_DECREMENT
            : LedgerAdjustment::OP_INCREMENT;
    }

    private function batchNo(): string
    {
        return 'REC'.now(config('ledger.timezone', 'Asia/Shanghai'))->format('YmdHis').Str::upper(Str::random(4));
    }

    private function sum(array $values): float
    {
        return array_sum($values);
    }

    private function decimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
