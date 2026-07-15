<?php

namespace App\Services\Ledger;

use App\Models\LedgerAdjustment;
use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\WithdrawalPayoutService;
use Throwable;

class RebateWithdrawalPayoutService implements WithdrawalPayoutService
{
    public function __construct(private readonly LedgerAdjustmentService $ledger) {}

    public function pay(RebateWithdrawal $withdrawal): array
    {
        if (! config('sub2api.admin_api.idempotency_verified', false)) {
            return [
                'ok' => false,
                'reference' => null,
                'response' => null,
                'error' => '尚未确认 Sub2API 调额接口的 Idempotency-Key 契约',
            ];
        }

        try {
            $adj = $this->ledger->adjustBusiness(
                $withdrawal->reviewer()->first(),
                [
                    'sub2api_user_id' => $withdrawal->user_id,
                    'operation' => LedgerAdjustment::OP_INCREMENT,
                    'amount' => (string) $withdrawal->quota_amount,
                    'cash_amount' => '0.00',
                    'gift_quota_amount' => '0.00',
                    'adjust_reason' => '返利提现',
                    'admin_notes' => '返利提现 '.$withdrawal->withdrawal_no,
                ],
                LedgerAdjustment::BUSINESS_REBATE_WITHDRAWAL,
                (string) $withdrawal->id,
                'rebate-withdrawal-'.$withdrawal->id,
            );
            $ok = $adj->status === LedgerAdjustment::STATUS_SUCCEEDED;

            return [
                'ok' => $ok,
                'reference' => $adj->ledger_no,
                'response' => ['ledger_adjustment' => $this->ledger->row($adj)],
                'error' => $ok ? null : ($adj->exception_reason ?: 'Sub2API 调额未确认成功'),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'reference' => null,
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
