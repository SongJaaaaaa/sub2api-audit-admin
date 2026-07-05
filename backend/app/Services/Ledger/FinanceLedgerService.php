<?php

namespace App\Services\Ledger;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\GiftQuotaEntry;
use App\Models\LedgerAdjustment;
use App\Models\OperationExpense;
use App\Services\Audit\AuditLogService;
use App\Support\Money;
use App\Support\SafeHtml;
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
        $this->filterUser($query, $filters);

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
            'created_at' => $row->created_at?->toDateTimeString(),
        ]);
    }

    public function gifts(array $filters, int $page, int $pageSize): array
    {
        $query = GiftQuotaEntry::query();
        $this->filterUser($query, $filters);

        return $this->page($query, $page, $pageSize, fn (GiftQuotaEntry $row): array => [
            'id' => $row->id,
            'entry_no' => $row->entry_no,
            'ledger_adjustment_id' => $row->ledger_adjustment_id,
            'sub2api_user_id' => $row->sub2api_user_id,
            'sub2api_user_email' => $row->sub2api_user_email,
            'quota_amount' => $row->quota_amount,
            'source' => $row->source,
            'remark' => $row->remark,
            'created_at' => $row->created_at?->toDateTimeString(),
        ]);
    }

    public function expenses(array $filters, int $page, int $pageSize): array
    {
        $query = OperationExpense::query();
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        return $this->page($query, $page, $pageSize, fn (OperationExpense $row): array => $this->expenseRow($row));
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
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }

    private function no(string $prefix): string
    {
        return $prefix.now('Asia/Shanghai')->format('YmdHis').Str::upper(Str::random(4));
    }

    private function filterUser($query, array $filters): void
    {
        $userId = (int) ($filters['sub2api_user_id'] ?? 0);
        if ($userId > 0) {
            $query->where('sub2api_user_id', $userId);
        }
    }

    private function page($query, int $page, int $pageSize, callable $row): array
    {
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()->map($row)->all();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }
}
