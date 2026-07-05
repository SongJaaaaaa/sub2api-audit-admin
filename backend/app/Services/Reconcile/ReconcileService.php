<?php

namespace App\Services\Reconcile;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\GiftQuotaEntry;
use App\Models\LedgerAdjustment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationDiff;
use App\Services\Audit\AuditLogService;
use App\Support\ChinaTime;
use App\Support\Money;
use Illuminate\Support\Str;

class ReconcileService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(Admin $admin, string $date): ReconciliationBatch
    {
        if (ReconciliationBatch::query()->whereDate('biz_date', $date)->exists()) {
            abort(409, '该日期已生成对账批次');
        }

        [$from, $to] = ChinaTime::dayRange($date);
        $adjs = LedgerAdjustment::query()
            ->where('status', LedgerAdjustment::STATUS_SUCCEEDED)
            ->where('confirmed_at', '>=', $from)
            ->where('confirmed_at', '<=', $to)
            ->get();

        $quota = '0.00';
        $delta = '0.00';
        foreach ($adjs as $adj) {
            $signed = $adj->operation === LedgerAdjustment::OP_DECREMENT
                ? Money::fmt(-1 * (float) $adj->amount)
                : Money::fmt($adj->amount);
            $quota = Money::add($quota, $signed);
            $delta = Money::add($delta, Money::sub($adj->after_balance, $adj->before_balance));
        }

        $cash = Money::fmt(CashEntry::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('cash_amount'));
        $gift = Money::fmt(GiftQuotaEntry::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('quota_amount'));
        $diff = Money::sub($quota, $delta);

        $batch = ReconciliationBatch::query()->create([
            'batch_no' => 'REC'.now('Asia/Shanghai')->format('YmdHis').Str::upper(Str::random(4)),
            'biz_date' => $date,
            'cash_total' => $cash,
            'quota_total' => $quota,
            'gift_total' => $gift,
            'sub2api_delta_total' => $delta,
            'diff_amount' => $diff,
            'status' => (float) $diff === 0.0 ? ReconciliationBatch::STATUS_BALANCED : ReconciliationBatch::STATUS_DIFF,
            'created_by' => $admin->id,
        ]);

        if ((float) $diff !== 0.0) {
            ReconciliationDiff::query()->create([
                'reconciliation_batch_id' => $batch->id,
                'type' => 'quota_delta',
                'title' => '本系统额度账与 Sub2API 已确认变动不一致',
                'amount' => $diff,
                'reason' => '需要人工复核当日成功调额记录',
            ]);
        }

        $this->audit->record($admin, 'reconcile.create', 'reconciliation_batch', $batch->id, null, $this->row($batch));

        return $batch;
    }

    public function list(int $page, int $pageSize): array
    {
        $query = ReconciliationBatch::query();
        $total = (clone $query)->count();
        $items = $query->orderByDesc('biz_date')->forPage($page, $pageSize)->get()
            ->map(fn (ReconciliationBatch $row): array => $this->row($row))
            ->all();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function diffs(ReconciliationBatch $batch): array
    {
        return ReconciliationDiff::query()
            ->where('reconciliation_batch_id', $batch->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ReconciliationDiff $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'title' => $row->title,
                'amount' => $row->amount,
                'reason' => $row->reason,
            ])->all();
    }

    public function row(ReconciliationBatch $row): array
    {
        return [
            'id' => $row->id,
            'batch_no' => $row->batch_no,
            'biz_date' => $row->biz_date?->format('Y-m-d'),
            'cash_total' => $row->cash_total,
            'quota_total' => $row->quota_total,
            'gift_total' => $row->gift_total,
            'sub2api_delta_total' => $row->sub2api_delta_total,
            'diff_amount' => $row->diff_amount,
            'status' => $row->status,
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }
}
