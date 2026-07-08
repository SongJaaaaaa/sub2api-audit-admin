<?php

namespace App\Services\Ledger;

use App\Support\ChinaTime;
use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Audit\AuditLogService;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Support\Money;
use App\Support\SafeHtml;
use App\Support\Sub2ApiNoteTag;
use RuntimeException;
use Throwable;

class LedgerAdjustmentService
{
    public function __construct(
        private readonly LedgerNumberService $numbers,
        private readonly Sub2ApiAdminClient $client,
        private readonly Sub2ApiBalanceVerifier $verifier,
        private readonly FinanceLedgerService $finance,
        private readonly AuditLogService $audit,
    ) {}

    public function adjust(Admin $admin, array $data): LedgerAdjustment
    {
        $ledgerNo = $this->numbers->make();
        $idempotencyKey = $this->numbers->idempotencyKey($ledgerNo);
        $amount = $this->money($data['amount']);
        [$cashAmount, $giftAmount] = $this->financeAmounts($amount, $data);
        $this->checkFinanceAmounts($amount, $cashAmount, $giftAmount);
        $adminNotes = SafeHtml::clean($data['admin_notes'] ?? null);
        $notes = Sub2ApiNoteTag::append($this->plainNotes($adminNotes), $ledgerNo, $idempotencyKey);

        $adj = LedgerAdjustment::query()->create([
            'ledger_no' => $ledgerNo,
            'idempotency_key' => $idempotencyKey,
            'sub2api_user_id' => (int) $data['sub2api_user_id'],
            'operation' => $data['operation'],
            'amount' => $amount,
            'cash_amount' => $cashAmount,
            'gift_quota_amount' => $giftAmount,
            'status' => LedgerAdjustment::STATUS_PENDING,
            'adjust_reason' => $data['adjust_reason'],
            'admin_notes' => $adminNotes,
            'sub2api_notes' => $notes,
            'created_by' => $admin->id,
        ]);

        try {
            $current = $this->verifier->currentBalance($adj->sub2api_user_id);
            $before = $current['balance'];

            if ($before === null) {
                throw new RuntimeException('Sub2API 用户余额为空，无法确认调额前余额');
            }

            $expected = $this->expectedBalance($before, $amount, $adj->operation);

            $adj->update([
                'sub2api_user_email' => $current['email'],
                'before_balance' => $before,
                'sub2api_request' => [
                    'balance' => $amount,
                    'operation' => $adj->operation,
                    'notes' => $notes,
                ],
            ]);

            $res = $this->client->updateUserBalance(
                $adj->sub2api_user_id,
                $amount,
                $adj->operation,
                $notes,
                $idempotencyKey,
            );

            $adj->update([
                'sub2api_response' => $res,
                'called_at' => now(),
            ]);

            $confirm = $this->verifier->verify($adj->sub2api_user_id, $expected);
            if (! $confirm['ok']) {
                $adj->update([
                    'status' => LedgerAdjustment::STATUS_EXCEPTION,
                    'after_balance' => $confirm['balance'],
                    'confirm_response' => $confirm['response'],
                    'exception_reason' => 'Sub2API 二次确认余额不一致',
                ]);
                $this->audit->record($admin, 'ledger_adjustment.exception', 'ledger_adjustment', $adj->id, null, $this->row($adj->refresh()));

                return $adj->refresh();
            }

            $adj->update([
                'status' => LedgerAdjustment::STATUS_SUCCEEDED,
                'after_balance' => $confirm['balance'],
                'confirm_response' => $confirm['response'],
                'confirmed_at' => now(),
            ]);
            $adj = $adj->refresh();
            $this->finance->recordAdjustment($admin, $adj);
            $this->audit->record($admin, 'ledger_adjustment.succeeded', 'ledger_adjustment', $adj->id, null, $this->row($adj));

            return $adj;
        } catch (Throwable $e) {
            $status = $adj->called_at
                ? LedgerAdjustment::STATUS_EXCEPTION
                : LedgerAdjustment::STATUS_VOIDED;

            $adj->update([
                'status' => $status,
                'exception_reason' => $e->getMessage(),
            ]);
            $action = $status === LedgerAdjustment::STATUS_VOIDED ? 'ledger_adjustment.voided' : 'ledger_adjustment.exception';
            $this->audit->record($admin, $action, 'ledger_adjustment', $adj->id, null, $this->row($adj->refresh()));

            return $adj->refresh();
        }
    }

    public function list(array $filters, int $page, int $pageSize): array
    {
        $query = LedgerAdjustment::query();

        $status = (string) ($filters['status'] ?? LedgerAdjustment::STATUS_SUCCEEDED);
        if ($status === 'abnormal') {
            $query->whereIn('status', [
                LedgerAdjustment::STATUS_VOIDED,
                LedgerAdjustment::STATUS_EXCEPTION,
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $userId = (int) ($filters['sub2api_user_id'] ?? 0);
        if ($userId > 0) {
            $query->where('sub2api_user_id', $userId);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('id')
            ->forPage($page, $pageSize)
            ->get()
            ->map(fn (LedgerAdjustment $adj): array => $this->row($adj))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function row(LedgerAdjustment $adj): array
    {
        return [
            'id' => $adj->id,
            'ledger_no' => $adj->ledger_no,
            'idempotency_key' => $adj->idempotency_key,
            'sub2api_user_id' => $adj->sub2api_user_id,
            'sub2api_user_email' => $adj->sub2api_user_email,
            'operation' => $adj->operation,
            'amount' => $adj->amount,
            'cash_amount' => $adj->cash_amount,
            'gift_quota_amount' => $adj->gift_quota_amount,
            'before_balance' => $adj->before_balance,
            'after_balance' => $adj->after_balance,
            'status' => $adj->status,
            'adjust_reason' => $adj->adjust_reason,
            'admin_notes' => $adj->admin_notes,
            'sub2api_notes' => $adj->sub2api_notes,
            'exception_reason' => $adj->exception_reason,
            'called_at' => ChinaTime::fmt($adj->called_at),
            'confirmed_at' => ChinaTime::fmt($adj->confirmed_at),
            'created_at' => ChinaTime::fmt($adj->created_at),
        ];
    }

    private function expectedBalance(string $before, string $amount, string $operation): string
    {
        $next = $operation === LedgerAdjustment::OP_DECREMENT
            ? (float) $before - (float) $amount
            : (float) $before + (float) $amount;

        return $this->money($next);
    }

    private function money(mixed $val): string
    {
        return Money::fmt($val);
    }

    private function checkFinanceAmounts(string $amount, string $cashAmount, string $giftAmount): void
    {
        if ((float) $cashAmount < 0 || (float) $giftAmount < 0) {
            throw new RuntimeException('入账金额不能大于 Sub2API 金额调整');
        }

        if ((float) $cashAmount <= 0 && (float) $giftAmount <= 0) {
            return;
        }

        if (Money::add($cashAmount, $giftAmount) !== $amount) {
            throw new RuntimeException('现金金额和赠送额度之和必须等于调额额度');
        }
    }

    private function financeAmounts(string $amount, array $data): array
    {
        if (($data['operation'] ?? '') === LedgerAdjustment::OP_DECREMENT || ($data['adjust_reason'] ?? '') === '异常修正') {
            return ['0.00', '0.00'];
        }

        if (($data['adjust_reason'] ?? '') === '补发') {
            return ['0.00', $amount];
        }

        $cashAmount = $this->money($data['cash_amount'] ?? 0);

        return [$cashAmount, $this->money((float) $amount - (float) $cashAmount)];
    }

    private function plainNotes(?string $html): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $text) ?? '';
    }
}
