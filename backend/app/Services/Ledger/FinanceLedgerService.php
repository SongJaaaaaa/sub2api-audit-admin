<?php

namespace App\Services\Ledger;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\GiftQuotaEntry;
use App\Models\LedgerAdjustment;
use App\Models\OperationExpense;
use App\Services\Audit\AuditLogService;
use App\Support\ChinaTime;
use App\Support\Money;
use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class FinanceLedgerService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function recordAdjustment(Admin $admin, LedgerAdjustment $adj): void
    {
        if ($adj->operation !== LedgerAdjustment::OP_INCREMENT) {
            return;
        }

        if ((float) $adj->cash_amount > 0) {
            CashEntry::query()->create([
                'entry_no' => $this->no('CASH'),
                'ledger_adjustment_id' => $adj->id,
                'sub2api_user_id' => $adj->sub2api_user_id,
                'sub2api_user_email' => $adj->sub2api_user_email,
                'direction' => CashEntry::DIR_IN,
                'cash_amount' => Money::fmt($adj->cash_amount),
                'source' => 'ledger_adjustment',
                'remark' => $adj->adjust_reason,
                'created_by' => $admin->id,
            ]);
        }

        if ((float) $adj->gift_quota_amount > 0) {
            GiftQuotaEntry::query()->create([
                'entry_no' => $this->no('GIFT'),
                'ledger_adjustment_id' => $adj->id,
                'sub2api_user_id' => $adj->sub2api_user_id,
                'sub2api_user_email' => $adj->sub2api_user_email,
                'quota_amount' => Money::fmt($adj->gift_quota_amount),
                'source' => 'ledger_adjustment',
                'remark' => $adj->adjust_reason,
                'created_by' => $admin->id,
            ]);
        }
    }

    public function cash(array $filters, int $page, int $pageSize): array
    {
        $query = CashEntry::query();
        $this->filterEntry($query, $filters);
        $summary = [
            'record_count' => (clone $query)->count(),
            'user_count' => (clone $query)->distinct()->count('sub2api_user_id'),
            'amount_total' => Money::fmt((clone $query)->sum('cash_amount')),
            'linked_count' => (clone $query)->whereNotNull('ledger_adjustment_id')->count(),
            'unlinked_count' => (clone $query)->whereNull('ledger_adjustment_id')->count(),
        ];

        return $this->page($query, $page, $pageSize, fn (CashEntry $row): array => [
            'id' => $row->id,
            'entry_no' => $row->entry_no,
            'ledger_adjustment_id' => $row->ledger_adjustment_id,
            'sub2api_user_id' => $row->sub2api_user_id,
            'sub2api_user_email' => $row->sub2api_user_email,
            'direction' => $row->direction,
            'cash_amount' => $row->cash_amount,
            'source' => $row->source,
            'remark' => $row->remark,
            'created_by' => $row->created_by,
            'operator_name' => $row->creator?->name,
            'operator_email' => $row->creator?->email,
            'created_at' => ChinaTime::fmt($row->created_at),
        ], $summary);
    }

    public function gifts(array $filters, int $page, int $pageSize): array
    {
        $query = GiftQuotaEntry::query();
        $this->filterEntry($query, $filters);
        $cashExists = fn (Builder $q) => $q->whereExists(function ($sub): void {
            $sub->selectRaw('1')->from('cash_entries')
                ->whereColumn('cash_entries.ledger_adjustment_id', 'gift_quota_entries.ledger_adjustment_id');
        });
        $cashMissing = fn (Builder $q) => $q->whereNotExists(function ($sub): void {
            $sub->selectRaw('1')->from('cash_entries')
                ->whereColumn('cash_entries.ledger_adjustment_id', 'gift_quota_entries.ledger_adjustment_id');
        });

        $summary = [
            'record_count' => (clone $query)->count(),
            'user_count' => (clone $query)->distinct()->count('sub2api_user_id'),
            'amount_total' => Money::fmt((clone $query)->sum('quota_amount')),
            'linked_count' => (clone $query)->whereNotNull('ledger_adjustment_id')->count(),
            'unlinked_count' => (clone $query)->whereNull('ledger_adjustment_id')->count(),
            'related_cash_count' => $cashExists(clone $query)->count(),
            'missing_cash_count' => $cashMissing(clone $query)->count(),
        ];

        return $this->page($query, $page, $pageSize, fn (GiftQuotaEntry $row): array => [
            'id' => $row->id,
            'entry_no' => $row->entry_no,
            'ledger_adjustment_id' => $row->ledger_adjustment_id,
            'sub2api_user_id' => $row->sub2api_user_id,
            'sub2api_user_email' => $row->sub2api_user_email,
            'quota_amount' => $row->quota_amount,
            'source' => $row->source,
            'remark' => $row->remark,
            'created_by' => $row->created_by,
            'operator_name' => $row->creator?->name,
            'operator_email' => $row->creator?->email,
            'has_related_cash' => $row->ledger_adjustment_id !== null && CashEntry::query()
                ->where('ledger_adjustment_id', $row->ledger_adjustment_id)->exists(),
            'created_at' => ChinaTime::fmt($row->created_at),
        ], $summary);
    }

    public function expenses(array $filters, int $page, int $pageSize): array
    {
        $query = OperationExpense::query();
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->where('category', $category);
        }
        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->where('paid_at', '>=', $from);
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->where('paid_at', '<=', $to);
        }
        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $query->where('created_by', $createdBy);
        }
        $minAmount = trim((string) ($filters['min_amount'] ?? ''));
        if ($minAmount !== '') {
            $query->where('amount', '>=', $minAmount);
        }
        $maxAmount = trim((string) ($filters['max_amount'] ?? ''));
        if ($maxAmount !== '') {
            $query->where('amount', '<=', $maxAmount);
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $sub) use ($keyword): void {
                $sub->where('remark', 'like', '%'.$keyword.'%')
                    ->orWhere('content_html', 'like', '%'.$keyword.'%');
            });
        }

        $total = (clone $query)->count();
        $amountTotal = Money::fmt((clone $query)->sum('amount'));
        $summary = [
            'record_count' => $total,
            'category_count' => (clone $query)->distinct()->count('category'),
            'amount_total' => $amountTotal,
            'max_amount' => Money::fmt((clone $query)->max('amount') ?? 0),
            'daily_average' => $from !== '' && $to !== ''
                ? Money::fmt((float) $amountTotal / (date_diff(date_create($from), date_create($to))->days + 1))
                : null,
        ];
        $categories = (clone $query)
            ->selectRaw('category, COUNT(*) as record_count, SUM(amount) as amount_total')
            ->groupBy('category')
            ->orderByDesc('amount_total')
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'record_count' => (int) $row->record_count,
                'amount_total' => Money::fmt($row->amount_total),
            ])
            ->all();

        $res = $this->page($query, $page, $pageSize, fn (OperationExpense $row): array => $this->expenseRow($row), $summary);
        $res['categories'] = $categories;

        return $res;
    }

    public function createExpense(Admin $admin, array $data): OperationExpense
    {
        $expense = OperationExpense::query()->create([
            'expense_no' => $this->no('EXP'),
            'category' => $data['category'],
            'amount' => Money::fmt($data['amount']),
            'paid_at' => $data['paid_at'],
            'remark' => $data['remark'] ?? null,
            'content_html' => SafeHtml::clean($data['content_html'] ?? null),
            'created_by' => $admin->id,
        ]);

        $this->audit->record($admin, 'operation_expense.create', 'operation_expense', $expense->id, null, $this->expenseRow($expense));

        return $expense;
    }

    public function expenseRow(OperationExpense $row): array
    {
        return [
            'id' => $row->id,
            'expense_no' => $row->expense_no,
            'category' => $row->category,
            'amount' => $row->amount,
            'paid_at' => $row->paid_at,
            'remark' => $row->remark,
            'content_html' => $row->content_html,
            'created_by' => $row->created_by,
            'operator_name' => $row->creator?->name,
            'operator_email' => $row->creator?->email,
            'created_at' => ChinaTime::fmt($row->created_at),
        ];
    }

    private function no(string $prefix): string
    {
        return $prefix.now('Asia/Shanghai')->format('YmdHis').Str::upper(Str::random(4));
    }

    private function filterEntry(Builder $query, array $filters): void
    {
        $userId = (int) ($filters['sub2api_user_id'] ?? 0);
        if ($userId > 0) {
            $query->where('sub2api_user_id', $userId);
        }
        $email = trim((string) ($filters['sub2api_user_email'] ?? ''));
        if ($email !== '') {
            $query->where('sub2api_user_email', 'like', '%'.$email.'%');
        }
        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $query->where('created_by', $createdBy);
        }
        $businessNo = trim((string) ($filters['business_no'] ?? ''));
        if ($businessNo !== '') {
            $query->where('entry_no', 'like', '%'.$businessNo.'%');
        }
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $query->where('created_at', '>=', $startDate.' 00:00:00');
        }
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $query->where('created_at', '<', date('Y-m-d 00:00:00', strtotime($endDate.' +1 day')));
        }
        $linkStatus = trim((string) ($filters['link_status'] ?? ''));
        if ($linkStatus === 'linked') {
            $query->whereNotNull('ledger_adjustment_id');
        } elseif ($linkStatus === 'unlinked') {
            $query->whereNull('ledger_adjustment_id');
        }
    }

    private function page(Builder $query, int $page, int $pageSize, callable $row, array $summary): array
    {
        $items = $query->with('creator:id,name,email')->orderByDesc('id')->forPage($page, $pageSize)->get()->map($row)->all();

        return [
            'items' => $items,
            'total' => $summary['record_count'],
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }
}
