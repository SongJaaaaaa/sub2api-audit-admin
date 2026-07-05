<?php

namespace App\Services\Ledger;

use App\Models\Admin;
use App\Models\LedgerAdjustment;
use App\Services\Sub2Api\Sub2ApiAdminClient;
use App\Support\Sub2ApiNoteTag;
use RuntimeException;
use Throwable;

class LedgerAdjustmentService
{
    public function __construct(
        private readonly LedgerNumberService $numbers,
        private readonly Sub2ApiAdminClient $client,
        private readonly Sub2ApiBalanceVerifier $verifier,
    ) {
    }

    public function adjust(Admin $admin, array $data): LedgerAdjustment
    {
        $ledgerNo = $this->numbers->make();
        $idempotencyKey = $this->numbers->idempotencyKey($ledgerNo);
        $amount = $this->money($data['amount']);
        $notes = Sub2ApiNoteTag::append((string) ($data['admin_notes'] ?? ''), $ledgerNo, $idempotencyKey);

        $adj = LedgerAdjustment::query()->create([
            'ledger_no' => $ledgerNo,
            'idempotency_key' => $idempotencyKey,
            'sub2api_user_id' => (int) $data['sub2api_user_id'],
            'operation' => $data['operation'],
            'amount' => $amount,
            'status' => LedgerAdjustment::STATUS_PENDING,
            'adjust_reason' => $data['adjust_reason'],
            'admin_notes' => $data['admin_notes'] ?? null,
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

                return $adj->refresh();
            }

            $adj->update([
                'status' => LedgerAdjustment::STATUS_SUCCEEDED,
                'after_balance' => $confirm['balance'],
                'confirm_response' => $confirm['response'],
                'confirmed_at' => now(),
            ]);

            return $adj->refresh();
        } catch (Throwable $e) {
            $status = $adj->called_at
                ? LedgerAdjustment::STATUS_EXCEPTION
                : LedgerAdjustment::STATUS_VOIDED;

            $adj->update([
                'status' => $status,
                'exception_reason' => $e->getMessage(),
            ]);

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
            'before_balance' => $adj->before_balance,
            'after_balance' => $adj->after_balance,
            'status' => $adj->status,
            'adjust_reason' => $adj->adjust_reason,
            'admin_notes' => $adj->admin_notes,
            'sub2api_notes' => $adj->sub2api_notes,
            'exception_reason' => $adj->exception_reason,
            'called_at' => $adj->called_at?->toDateTimeString(),
            'confirmed_at' => $adj->confirmed_at?->toDateTimeString(),
            'created_at' => $adj->created_at?->toDateTimeString(),
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
        return number_format((float) $val, 2, '.', '');
    }
}
