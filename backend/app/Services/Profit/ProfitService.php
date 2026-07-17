<?php

namespace App\Services\Profit;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\OperationExpense;
use App\Models\ProfitSettlement;
use App\Models\ProfitSettlementItem;
use App\Services\Audit\AuditLogService;
use App\Support\ChinaTime;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfitService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function summary(string $startDate, string $endDate): array
    {
        $range = ChinaTime::range($startDate, $endDate);
        $income = $this->incomeQuery($startDate, $endDate)
            ->selectRaw('DATE(created_at) as biz_date, created_by as owner_id, COUNT(*) as record_count, SUM(cash_amount) as amount_total')
            ->groupByRaw('DATE(created_at), created_by')
            ->get();
        $expenses = $this->expenseQuery($startDate, $endDate)
            ->selectRaw('paid_at as biz_date, created_by as owner_id, COUNT(*) as record_count, SUM(amount) as amount_total')
            ->groupBy('paid_at', 'created_by')
            ->get();
        $ownerIds = $income->pluck('owner_id')
            ->merge($expenses->pluck('owner_id'))
            ->map(fn ($id): int => (int) ($id ?? 0))
            ->unique()
            ->sort()
            ->values();
        $admins = Admin::query()->whereIn('id', $ownerIds)->get(['id', 'name', 'email'])->keyBy('id');
        $incomeByOwner = $income->groupBy(fn ($row): int => (int) ($row->owner_id ?? 0));
        $expensesByOwner = $expenses->groupBy(fn ($row): int => (int) ($row->owner_id ?? 0));
        $owners = $ownerIds->map(function ($id) use ($admins, $incomeByOwner, $expensesByOwner): array {
            $id = (int) $id;
            $admin = $admins->get((int) $id);
            $ownerIncome = $incomeByOwner->get($id, collect());
            $ownerExpenses = $expensesByOwner->get($id, collect());

            return [
                'id' => $id,
                'name' => $admin?->name ?? '未知管理员',
                'email' => $admin?->email,
                'income_total' => Money::sum($ownerIncome->pluck('amount_total')),
                'income_count' => (int) $ownerIncome->sum('record_count'),
                'expense_total' => Money::sum($ownerExpenses->pluck('amount_total')),
                'expense_count' => (int) $ownerExpenses->sum('record_count'),
            ];
        })->values()->all();
        $days = collect($range->dates())->mapWithKeys(fn (string $date): array => [$date => [
            'biz_date' => $date,
            'income_by_owner' => [],
            'expense_by_owner' => [],
            'income_total' => '0.00',
            'expense_total' => '0.00',
            'profit_total' => '0.00',
            'income_count' => 0,
            'expense_count' => 0,
        ]]);

        foreach ($income as $row) {
            $date = (string) $row->biz_date;
            $day = $days->get($date);
            $ownerId = (int) ($row->owner_id ?? 0);
            $day['income_by_owner'][$ownerId] = Money::fmt($row->amount_total);
            $day['income_total'] = Money::add($day['income_total'], $row->amount_total);
            $day['income_count'] += (int) $row->record_count;
            $days->put($date, $day);
        }
        foreach ($expenses as $row) {
            $date = (string) $row->biz_date;
            $day = $days->get($date);
            $ownerId = (int) ($row->owner_id ?? 0);
            $day['expense_by_owner'][$ownerId] = Money::fmt($row->amount_total);
            $day['expense_total'] = Money::add($day['expense_total'], $row->amount_total);
            $day['expense_count'] += (int) $row->record_count;
            $days->put($date, $day);
        }

        $rows = $days->map(function (array $day): array {
            $day['profit_total'] = Money::sub($day['income_total'], $day['expense_total']);
            $day['income_by_owner'] = (object) $day['income_by_owner'];
            $day['expense_by_owner'] = (object) $day['expense_by_owner'];

            return $day;
        })->values();
        $incomeTotal = Money::sum($rows->pluck('income_total'));
        $expenseTotal = Money::sum($rows->pluck('expense_total'));
        $pendingIncome = $this->pendingIncomeQuery($startDate, $endDate)
            ->selectRaw('COUNT(*) as record_count, COALESCE(SUM(cash_amount), 0) as amount_total')
            ->first();
        $pendingExpenses = $this->pendingExpenseQuery($startDate, $endDate)
            ->selectRaw('COUNT(*) as record_count, COALESCE(SUM(amount), 0) as amount_total')
            ->first();
        $pendingIncomeTotal = Money::fmt($pendingIncome->amount_total);
        $pendingExpenseTotal = Money::fmt($pendingExpenses->amount_total);

        return [
            'owners' => $owners,
            'days' => $rows->all(),
            'summary' => [
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'profit_total' => Money::sub($incomeTotal, $expenseTotal),
                'income_count' => $rows->sum('income_count'),
                'expense_count' => $rows->sum('expense_count'),
            ],
            'pending_summary' => [
                'income_total' => $pendingIncomeTotal,
                'expense_total' => $pendingExpenseTotal,
                'profit_total' => Money::sub($pendingIncomeTotal, $pendingExpenseTotal),
                'income_count' => (int) $pendingIncome->record_count,
                'expense_count' => (int) $pendingExpenses->record_count,
            ],
        ];
    }

    public function details(string $date): array
    {
        $income = $this->incomeQuery($date, $date)->with('creator:id,name,email')->orderBy('id')->get()
            ->map(fn (CashEntry $row): array => [
                'id' => $row->id,
                'entry_no' => $row->entry_no,
                'sub2api_user_id' => $row->sub2api_user_id,
                'sub2api_user_email' => $row->sub2api_user_email,
                'amount' => $row->cash_amount,
                'owner_admin_id' => $row->created_by,
                'owner_name' => $this->ownerName($row->creator),
                'remark' => $row->remark,
                'biz_date' => substr((string) $row->created_at, 0, 10),
                'created_at' => ChinaTime::fmt($row->created_at),
            ])->all();
        $expenses = $this->expenseQuery($date, $date)->with('creator:id,name,email')->orderBy('id')->get()
            ->map(fn (OperationExpense $row): array => [
                'id' => $row->id,
                'expense_no' => $row->expense_no,
                'category' => $row->category,
                'amount' => $row->amount,
                'owner_admin_id' => $row->created_by,
                'owner_name' => $this->ownerName($row->creator),
                'remark' => $row->remark,
                'biz_date' => $row->paid_at,
                'created_at' => ChinaTime::fmt($row->created_at),
            ])->all();

        return ['income' => $income, 'expenses' => $expenses];
    }

    public function settle(Admin $admin, string $startDate, string $endDate): ProfitSettlement
    {
        $batch = DB::transaction(function () use ($admin, $startDate, $endDate): ProfitSettlement {
            $income = $this->pendingIncomeQuery($startDate, $endDate)->with('creator:id,name,email')->lockForUpdate()->get();
            $expenses = $this->pendingExpenseQuery($startDate, $endDate)->with('creator:id,name,email')->lockForUpdate()->get();
            if ($income->isEmpty() && $expenses->isEmpty()) {
                abort(422, '该日期范围没有待分账流水');
            }

            $incomeTotal = Money::sum($income->pluck('cash_amount'));
            $expenseTotal = Money::sum($expenses->pluck('amount'));
            $batch = ProfitSettlement::query()->create([
                'batch_no' => 'PST'.now('Asia/Shanghai')->format('YmdHis').Str::upper(Str::random(4)),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'profit_total' => Money::sub($incomeTotal, $expenseTotal),
                'income_count' => $income->count(),
                'expense_count' => $expenses->count(),
                'status' => ProfitSettlement::STATUS_CONFIRMED,
                'created_by' => $admin->id,
            ]);

            foreach ($income as $row) {
                $batch->items()->create([
                    'item_type' => ProfitSettlementItem::TYPE_INCOME,
                    'item_id' => $row->id,
                    'biz_date' => substr((string) $row->created_at, 0, 10),
                    'owner_admin_id' => $row->created_by,
                    'owner_name' => $this->ownerName($row->creator),
                    'reference_no' => $row->entry_no,
                    'description' => $row->sub2api_user_email ?: $row->remark,
                    'amount' => $row->cash_amount,
                ]);
            }
            foreach ($expenses as $row) {
                $batch->items()->create([
                    'item_type' => ProfitSettlementItem::TYPE_EXPENSE,
                    'item_id' => $row->id,
                    'biz_date' => $row->paid_at,
                    'owner_admin_id' => $row->created_by,
                    'owner_name' => $this->ownerName($row->creator),
                    'reference_no' => $row->expense_no,
                    'description' => $row->category.($row->remark ? ' · '.$row->remark : ''),
                    'amount' => $row->amount,
                ]);
            }

            CashEntry::query()->whereKey($income->modelKeys())->update(['profit_settlement_id' => $batch->id]);
            OperationExpense::query()->whereKey($expenses->modelKeys())->update(['profit_settlement_id' => $batch->id]);

            return $batch->refresh();
        });

        $this->audit->record($admin, 'profit_settlement.confirm', 'profit_settlement', $batch->id, null, $this->row($batch));

        return $batch;
    }

    public function reverse(Admin $admin, ProfitSettlement $settlement): ProfitSettlement
    {
        $before = $this->row($settlement);
        $batch = DB::transaction(function () use ($admin, $settlement): ProfitSettlement {
            $batch = ProfitSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($batch->status === ProfitSettlement::STATUS_REVERSED) {
                abort(422, '该分账批次已撤销');
            }

            CashEntry::query()->where('profit_settlement_id', $batch->id)->update(['profit_settlement_id' => null]);
            OperationExpense::query()->where('profit_settlement_id', $batch->id)->update(['profit_settlement_id' => null]);
            $batch->update([
                'status' => ProfitSettlement::STATUS_REVERSED,
                'reversed_by' => $admin->id,
                'reversed_at' => now(),
            ]);

            return $batch->refresh();
        });

        $this->audit->record($admin, 'profit_settlement.reverse', 'profit_settlement', $batch->id, $before, $this->row($batch));

        return $batch;
    }

    public function settlements(array $filters, int $page, int $pageSize): array
    {
        $query = ProfitSettlement::query()->with(['creator:id,name,email', 'reverser:id,name,email']);
        if (! empty($filters['start_date'])) {
            $query->where('end_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('start_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (ProfitSettlement $row): array => $this->row($row))->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function items(ProfitSettlement $settlement): array
    {
        return $settlement->items()->orderBy('biz_date')->orderBy('item_type')->orderBy('id')->get()
            ->map(fn (ProfitSettlementItem $row): array => [
                'id' => $row->id,
                'item_type' => $row->item_type,
                'item_id' => $row->item_id,
                'biz_date' => $row->biz_date?->toDateString(),
                'owner_admin_id' => $row->owner_admin_id,
                'owner_name' => $row->owner_name,
                'reference_no' => $row->reference_no,
                'description' => $row->description,
                'amount' => $row->amount,
            ])->all();
    }

    public function row(ProfitSettlement $row): array
    {
        $row->loadMissing(['creator:id,name,email', 'reverser:id,name,email']);

        return [
            'id' => $row->id,
            'batch_no' => $row->batch_no,
            'start_date' => $row->start_date?->toDateString(),
            'end_date' => $row->end_date?->toDateString(),
            'income_total' => $row->income_total,
            'expense_total' => $row->expense_total,
            'profit_total' => $row->profit_total,
            'income_count' => $row->income_count,
            'expense_count' => $row->expense_count,
            'status' => $row->status,
            'created_by' => $row->created_by,
            'operator_name' => $row->creator?->name,
            'reversed_by' => $row->reversed_by,
            'reverser_name' => $row->reverser?->name,
            'reversed_at' => ChinaTime::fmt($row->reversed_at),
            'created_at' => ChinaTime::fmt($row->created_at),
        ];
    }

    private function incomeQuery(string $startDate, string $endDate): Builder
    {
        $range = ChinaTime::range($startDate, $endDate);

        return CashEntry::query()
            ->where('profit_eligible', true)
            ->where('created_at', '>=', $range->localStartText())
            ->where('created_at', '<', $range->localEndExclusiveText());
    }

    private function expenseQuery(string $startDate, string $endDate): Builder
    {
        ChinaTime::range($startDate, $endDate);

        return OperationExpense::query()
            ->where('profit_eligible', true)
            ->where('paid_at', '>=', $startDate)
            ->where('paid_at', '<=', $endDate);
    }

    private function pendingIncomeQuery(string $startDate, string $endDate): Builder
    {
        return $this->incomeQuery($startDate, $endDate)->whereNull('profit_settlement_id');
    }

    private function pendingExpenseQuery(string $startDate, string $endDate): Builder
    {
        return $this->expenseQuery($startDate, $endDate)->whereNull('profit_settlement_id');
    }

    private function ownerName(?Admin $admin): string
    {
        return $admin?->name ?? $admin?->email ?? '未知管理员';
    }
}
