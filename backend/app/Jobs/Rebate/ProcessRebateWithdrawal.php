<?php

namespace App\Jobs\Rebate;

use App\Models\Rebate\RebateWithdrawal;
use App\Services\Rebate\BalanceService;
use App\Services\Rebate\WithdrawalPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessRebateWithdrawal implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $withdrawalId) {}

    public function uniqueId(): string
    {
        return (string) $this->withdrawalId;
    }

    public function handle(WithdrawalPayoutService $payout, BalanceService $balances): void
    {
        $withdrawal = RebateWithdrawal::query()->findOrFail($this->withdrawalId);
        if ($withdrawal->status !== RebateWithdrawal::STATUS_PROCESSING) {
            return;
        }

        try {
            $result = $payout->pay($withdrawal);
            if (($result['ok'] ?? false) !== true) {
                throw new RuntimeException((string) ($result['error'] ?? 'Sub2API 到账失败'));
            }

            DB::transaction(function () use ($balances, $result): void {
                $withdrawal = RebateWithdrawal::query()->lockForUpdate()->findOrFail($this->withdrawalId);
                if ($withdrawal->status !== RebateWithdrawal::STATUS_PROCESSING) {
                    return;
                }

                $balances->completeWithdrawal(
                    $withdrawal->user_id,
                    $withdrawal->amount,
                    'rebate_withdrawal',
                    $withdrawal->withdrawal_no,
                    '已转入 Sub2API 额度',
                    ['payout_reference' => $result['reference'] ?? null],
                );
                $withdrawal->update([
                    'status' => RebateWithdrawal::STATUS_SUCCEEDED,
                    'payout_reference' => $result['reference'] ?? null,
                    'payout_response' => $result['response'] ?? [],
                    'exception_reason' => null,
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($e): void {
                $withdrawal = RebateWithdrawal::query()->lockForUpdate()->find($this->withdrawalId);
                if ($withdrawal && $withdrawal->status === RebateWithdrawal::STATUS_PROCESSING) {
                    $withdrawal->update([
                        'status' => RebateWithdrawal::STATUS_EXCEPTION,
                        'exception_reason' => $e->getMessage(),
                    ]);
                }
            });
        }
    }
}
