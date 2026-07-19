<?php

namespace App\Services\Ledger;

use App\Support\ChinaTime;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceHistoryService
{
    public function paginate(array $filters, int $page, int $pageSize): array
    {
        $query = $this->query($filters);
        $total = (clone $query)->count();
        $summary = [
            'record_count' => $total,
            'income_count' => 0,
            'expense_count' => 0,
            'gift_count' => 0,
            'income_total' => '0.00',
            'expense_total' => '0.00',
            'gift_total' => '0.00',
        ];

        foreach ((clone $query)
            ->selectRaw('type, COUNT(*) as record_count, COALESCE(SUM(amount), 0) as amount_total')
            ->groupBy('type')
            ->get() as $row) {
            $summary[$row->type.'_count'] = (int) $row->record_count;
            $summary[$row->type.'_total'] = Money::fmt($row->amount_total);
        }

        $items = (clone $query)
            ->orderByDesc('biz_date')
            ->orderByDesc('created_at')
            ->orderByDesc('source_id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn ($row): array => $this->row($row))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'summary' => $summary,
        ];
    }

    public function all(array $filters): array
    {
        return $this->query($filters)
            ->orderByDesc('biz_date')
            ->orderByDesc('created_at')
            ->orderByDesc('source_id')
            ->get()
            ->map(fn ($row): array => $this->row($row))
            ->all();
    }

    private function query(array $filters): Builder
    {
        $type = (string) ($filters['type'] ?? '');
        $parts = [];

        if ($type === '' || $type === 'income') {
            $parts[] = $this->incomeQuery($filters);
        }
        if ($type === '' || $type === 'expense') {
            $parts[] = $this->expenseQuery($filters);
        }
        if ($type === '' || $type === 'gift') {
            $parts[] = $this->giftQuery($filters);
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'finance_history');
    }

    private function incomeQuery(array $filters): Builder
    {
        $query = DB::table('cash_entries as row')
            ->leftJoin('admins as admin', 'admin.id', '=', 'row.created_by')
            ->selectRaw("'income' as type")
            ->selectRaw('row.id as source_id, row.entry_no as bill_no, COALESCE(row.received_at, DATE(row.created_at)) as biz_date')
            ->selectRaw('row.sub2api_user_id, row.sub2api_user_email, NULL as category')
            ->selectRaw('row.cash_amount as amount, row.created_by, admin.name as operator_name, admin.email as operator_email')
            ->selectRaw('row.remark, row.created_at');

        $this->commonFilters($query, $filters, DB::raw('COALESCE(row.received_at, DATE(row.created_at))'), 'row', true);

        return $query;
    }

    private function giftQuery(array $filters): Builder
    {
        $query = DB::table('gift_quota_entries as row')
            ->leftJoin('admins as admin', 'admin.id', '=', 'row.created_by')
            ->selectRaw("'gift' as type")
            ->selectRaw('row.id as source_id, row.entry_no as bill_no, DATE(row.created_at) as biz_date')
            ->selectRaw('row.sub2api_user_id, row.sub2api_user_email, NULL as category')
            ->selectRaw('row.quota_amount as amount, row.created_by, admin.name as operator_name, admin.email as operator_email')
            ->selectRaw('row.remark, row.created_at');

        $this->commonFilters($query, $filters, 'row.created_at', 'row');

        return $query;
    }

    private function expenseQuery(array $filters): Builder
    {
        $query = DB::table('operation_expenses as row')
            ->leftJoin('admins as admin', 'admin.id', '=', 'row.created_by')
            ->selectRaw("'expense' as type")
            ->selectRaw('row.id as source_id, row.expense_no as bill_no, row.paid_at as biz_date')
            ->selectRaw('NULL as sub2api_user_id, NULL as sub2api_user_email, row.category')
            ->selectRaw('row.amount, row.created_by, admin.name as operator_name, admin.email as operator_email')
            ->selectRaw('row.remark, row.created_at');

        $this->commonFilters($query, $filters, 'row.paid_at', 'row', true, true);

        return $query;
    }

    private function commonFilters(Builder $query, array $filters, mixed $dateColumn, string $alias, bool $dateOnly = false, bool $expense = false): void
    {
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $query->where($dateColumn, '>=', $dateOnly ? $startDate : $startDate.' 00:00:00');
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $query->where($dateColumn, $dateOnly ? '<=' : '<', $dateOnly
                ? $endDate
                : date('Y-m-d 00:00:00', strtotime($endDate.' +1 day')));
        }

        $createdBy = (int) ($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $query->where($alias.'.created_by', $createdBy);
        }

        $userId = (int) ($filters['sub2api_user_id'] ?? 0);
        if ($userId > 0) {
            $expense
                ? $query->whereRaw('1 = 0')
                : $query->where($alias.'.sub2api_user_id', $userId);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword === '') {
            return;
        }

        $query->where(function (Builder $sub) use ($alias, $keyword, $expense): void {
            $bill = $expense ? 'expense_no' : 'entry_no';
            $sub->where($alias.'.'.$bill, 'like', '%'.$keyword.'%')
                ->orWhere($alias.'.remark', 'like', '%'.$keyword.'%');
            if ($expense) {
                $sub->orWhere($alias.'.category', 'like', '%'.$keyword.'%');
            } else {
                $sub->orWhere($alias.'.sub2api_user_email', 'like', '%'.$keyword.'%');
            }
        });
    }

    private function row(object $row): array
    {
        return [
            'type' => $row->type,
            'source_id' => (int) $row->source_id,
            'bill_no' => $row->bill_no,
            'biz_date' => (string) $row->biz_date,
            'sub2api_user_id' => $row->sub2api_user_id === null ? null : (int) $row->sub2api_user_id,
            'sub2api_user_email' => $row->sub2api_user_email,
            'category' => $row->category,
            'amount' => Money::fmt($row->amount),
            'created_by' => $row->created_by === null ? null : (int) $row->created_by,
            'operator_name' => $row->operator_name,
            'operator_email' => $row->operator_email,
            'remark' => $row->remark,
            'created_at' => ChinaTime::fmt($row->created_at),
        ];
    }
}
