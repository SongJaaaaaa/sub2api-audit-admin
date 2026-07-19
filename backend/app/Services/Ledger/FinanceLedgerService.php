<?php

namespace App\Services\Ledger;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\GiftQuotaEntry;
use App\Models\LedgerAdjustment;
use App\Models\OperationExpense;
use App\Services\Audit\AuditLogService;
use App\Services\Sub2Api\Sub2ApiReadRepository;
use App\Support\ChinaDateRange;
use App\Support\ChinaTime;
use App\Support\Money;
use App\Support\SafeHtml;
use App\Support\Sub2ApiNoteTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceLedgerService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly Sub2ApiReadRepository $read,
    ) {}

    public function recordAdjustment(?Admin $admin, LedgerAdjustment $adj): void
    {
        if ($adj->operation !== LedgerAdjustment::OP_INCREMENT) {
            return;
        }

        if (bccomp((string) $adj->cash_amount, '0', 2) > 0) {
            CashEntry::query()->firstOrCreate([
                'ledger_adjustment_id' => $adj->id,
            ], [
                'entry_no' => $this->no('CASH'),
                'sub2api_user_id' => $adj->sub2api_user_id,
                'sub2api_user_email' => $adj->sub2api_user_email,
                'direction' => CashEntry::DIR_IN,
                'cash_amount' => Money::fmt($adj->cash_amount),
                'source' => 'ledger_adjustment',
                'remark' => $adj->adjust_reason,
                'profit_eligible' => true,
                'created_by' => $admin?->id,
            ]);
        }

        if (bccomp((string) $adj->gift_quota_amount, '0', 2) > 0) {
            GiftQuotaEntry::query()->firstOrCreate([
                'ledger_adjustment_id' => $adj->id,
            ], [
                'entry_no' => $this->no('GIFT'),
                'sub2api_user_id' => $adj->sub2api_user_id,
                'sub2api_user_email' => $adj->sub2api_user_email,
                'quota_amount' => Money::fmt($adj->gift_quota_amount),
                'source' => 'ledger_adjustment',
                'remark' => $adj->adjust_reason,
                'created_by' => $admin?->id,
            ]);
        }
    }

    public function userSummary(int $userId): array
    {
        return [
            'total_recharge' => Money::fmt(CashEntry::query()
                ->where('sub2api_user_id', $userId)
                ->where('direction', CashEntry::DIR_IN)
                ->sum('cash_amount')),
            'total_gift' => Money::fmt(GiftQuotaEntry::query()
                ->where('sub2api_user_id', $userId)
                ->sum('quota_amount')),
        ];
    }

    public function cash(array $filters, int $page, int $pageSize): array
    {
        $today = now(config('ledger.timezone', 'Asia/Shanghai'))->toDateString();
        $start = trim((string) ($filters['start_date'] ?? '')) ?: $today;
        $end = trim((string) ($filters['end_date'] ?? '')) ?: $start;
        $this->syncExternalIncome($start, $end);
        $query = CashEntry::query()->with('adjustment:id,admin_notes');
        $this->filterEntry($query, $filters);
        $summary = [
            'record_count' => (clone $query)->count(),
            'user_count' => (clone $query)->distinct()->count('sub2api_user_id'),
            'amount_total' => Money::fmt((clone $query)->sum('cash_amount')),
            'linked_count' => (clone $query)->whereNotNull('ledger_adjustment_id')->count(),
            'unlinked_count' => (clone $query)->whereNull('ledger_adjustment_id')->count(),
        ];

        return $this->page($query, $page, $pageSize, fn (CashEntry $row): array => $this->cashRow($row), $summary);
    }

    public function createIncome(Admin $admin, array $data): CashEntry
    {
        $income = CashEntry::query()->create([
            'entry_no' => $this->no('CASH'),
            'direction' => CashEntry::DIR_IN,
            'cash_amount' => Money::fmt($data['amount']),
            'received_at' => $data['received_at'],
            'source' => 'manual_income',
            'content_html' => SafeHtml::clean($data['content_html'] ?? null),
            'profit_eligible' => true,
            'created_by' => $admin->id,
        ]);

        $this->audit->record($admin, 'cash_entry.create', 'cash_entry', $income->id, null, $this->cashRow($income));

        return $income;
    }

    public function cashRow(CashEntry $row): array
    {
        $row->loadMissing(['creator:id,name,email', 'adjustment:id,admin_notes']);

        return [
            'id' => $row->id,
            'entry_no' => $row->entry_no,
            'ledger_adjustment_id' => $row->ledger_adjustment_id,
            'sub2api_user_id' => $row->sub2api_user_id,
            'sub2api_user_email' => $row->sub2api_user_email,
            'direction' => $row->direction,
            'cash_amount' => $row->cash_amount,
            'received_at' => $row->received_at?->format('Y-m-d') ?? substr((string) $row->created_at, 0, 10),
            'source' => $row->source,
            'remark' => $row->remark,
            'content_html' => $row->content_html ?: $row->adjustment?->admin_notes,
            'created_by' => $row->created_by,
            'operator_name' => $row->creator?->name,
            'operator_email' => $row->creator?->email,
            'created_at' => ChinaTime::fmt($row->created_at),
        ];
    }

    public function syncExternalIncome(string $start, string $end): int
    {
        $range = ChinaDateRange::make($start, $end);
        $events = collect($this->read->adminAdjustmentEvents($range->utcStart, $range->utcEndExclusive));
        $sourceIds = $events->pluck('remote_event_id')->all();
        $keys = $events
            ->map(fn (array $row): ?string => Sub2ApiNoteTag::idempotencyKey($row['notes'] ?? null))
            ->filter()
            ->values()
            ->all();
        $adjs = LedgerAdjustment::query()
            ->whereIn('sub2api_source_id', $sourceIds)
            ->orWhereIn('idempotency_key', $keys)
            ->get();
        $bySource = $adjs->whereNotNull('sub2api_source_id')->keyBy('sub2api_source_id');
        $byPair = $adjs->whereNull('sub2api_source_id')
            ->keyBy(fn (LedgerAdjustment $row): string => $row->sub2api_user_id.':'.$row->idempotency_key);
        $created = 0;

        foreach ($events as $event) {
            $tag = Sub2ApiNoteTag::parse($event['notes'] ?? null);
            $linked = $bySource->has($event['remote_event_id'])
                || ($tag['idempotency_key'] && $byPair->has($event['sub2api_user_id'].':'.$tag['idempotency_key']));
            if ((float) $event['value'] <= 0 || $linked || $tag['is_audit']) {
                continue;
            }

            $time = ChinaTime::fmtUtc($event['event_at']);
            $created += DB::table('cash_entries')->insertOrIgnore([
                'entry_no' => 'SUB2EXT'.$event['remote_event_id'],
                'sub2api_user_id' => $event['sub2api_user_id'],
                'sub2api_user_email' => $event['user_email'],
                'direction' => CashEntry::DIR_IN,
                'cash_amount' => Money::fmt($event['value']),
                'source' => 'sub2api_external_adjustment',
                'remark' => 'sub2api外部调整',
                'profit_eligible' => true,
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }

        return $created;
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
                ? bcdiv($amountTotal, (string) (date_diff(date_create($from), date_create($to))->days + 1), 2)
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
            'category' => $data['category'] ?? '其他',
            'amount' => Money::fmt($data['amount']),
            'paid_at' => $data['paid_at'],
            'remark' => $data['remark'] ?? null,
            'content_html' => SafeHtml::clean($data['content_html'] ?? null),
            'profit_eligible' => true,
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
        $date = DB::raw('COALESCE(received_at, DATE(created_at))');
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $query->where($date, '>=', $startDate);
        }
        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $query->where($date, '<=', $endDate);
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
