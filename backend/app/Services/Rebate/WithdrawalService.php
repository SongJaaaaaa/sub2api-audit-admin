<?php

namespace App\Services\Rebate;

use App\Jobs\Rebate\ProcessRebateWithdrawal;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class WithdrawalService
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly BalanceService $balances,
    ) {}

    public function request(RebateUser $user, int|string $value, ?string $remark = null): RebateWithdrawal
    {
        $amount = RebateMoney::positive($value);
        $config = $this->configs->get();
        if (RebateMoney::compare($amount, $config->withdraw_min_amount) < 0) {
            throw new RuntimeException('提现金额不能低于 '.$config->withdraw_min_amount);
        }
        if (RebateMoney::compare($config->withdraw_to_api_quota_rate, '0.00') <= 0) {
            throw new LogicException('提现换算比例必须大于 0');
        }

        return DB::transaction(function () use ($user, $amount, $remark, $config): RebateWithdrawal {
            $this->balances->lockForUpdate($user->id);
            $today = $this->todayUsage($user->id);
            if ($config->withdraw_daily_limit > 0 && $today['count'] >= $config->withdraw_daily_limit) {
                throw new RuntimeException('今日提现次数已达上限');
            }

            if (RebateMoney::compare($config->withdraw_daily_amount_limit, '0.00') > 0
                && RebateMoney::compare(RebateMoney::add($today['amount'], $amount), $config->withdraw_daily_amount_limit) > 0) {
                throw new RuntimeException('今日提现金额已达上限');
            }

            $no = 'RBW-'.Str::uuid()->toString();
            $this->balances->freeze(
                $user->id,
                $amount,
                'rebate_withdrawal',
                $no,
                '申请转入 Sub2API 额度',
            );

            return RebateWithdrawal::query()->create([
                'withdrawal_no' => $no,
                'user_id' => $user->id,
                'amount' => $amount,
                'quota_amount' => RebateMoney::multiply($amount, $config->withdraw_to_api_quota_rate),
                'status' => RebateWithdrawal::STATUS_PENDING,
                'remark' => $remark,
                'requested_at' => now(),
            ]);
        });
    }

    public function approve(RebateWithdrawal $source, ?int $adminId = null): RebateWithdrawal
    {
        return DB::transaction(function () use ($source, $adminId): RebateWithdrawal {
            $withdrawal = RebateWithdrawal::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertWritable($withdrawal);
            if ($withdrawal->status === RebateWithdrawal::STATUS_PROCESSING
                || $withdrawal->status === RebateWithdrawal::STATUS_SUCCEEDED) {
                return $withdrawal;
            }
            if ($withdrawal->status !== RebateWithdrawal::STATUS_PENDING) {
                throw new LogicException('只有待审核提现可以通过');
            }

            $withdrawal->update([
                'status' => RebateWithdrawal::STATUS_PROCESSING,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'processing_started_at' => now(),
                'attempts' => $withdrawal->attempts + 1,
                'exception_reason' => null,
            ]);
            ProcessRebateWithdrawal::dispatch($withdrawal->id)->afterCommit();

            return $withdrawal->refresh();
        });
    }

    public function reject(
        RebateWithdrawal $source,
        ?int $adminId = null,
        ?string $reason = null,
    ): RebateWithdrawal {
        return DB::transaction(function () use ($source, $adminId, $reason): RebateWithdrawal {
            $withdrawal = RebateWithdrawal::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertWritable($withdrawal);
            if ($withdrawal->status === RebateWithdrawal::STATUS_REJECTED) {
                return $withdrawal;
            }
            if ($withdrawal->status !== RebateWithdrawal::STATUS_PENDING) {
                throw new LogicException('只有待审核提现可以拒绝');
            }

            $this->balances->unfreeze(
                $withdrawal->user_id,
                $withdrawal->amount,
                'rebate_withdrawal',
                $withdrawal->withdrawal_no,
                '提现审核拒绝',
            );
            $withdrawal->update([
                'status' => RebateWithdrawal::STATUS_REJECTED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ]);

            return $withdrawal->refresh();
        });
    }

    public function retry(RebateWithdrawal $source, ?int $adminId = null): RebateWithdrawal
    {
        return DB::transaction(function () use ($source, $adminId): RebateWithdrawal {
            $withdrawal = RebateWithdrawal::query()->lockForUpdate()->findOrFail($source->id);
            $this->assertWritable($withdrawal);
            if ($withdrawal->status === RebateWithdrawal::STATUS_PROCESSING) {
                return $withdrawal;
            }
            if ($withdrawal->status !== RebateWithdrawal::STATUS_EXCEPTION) {
                throw new LogicException('只有异常提现可以重试');
            }

            $withdrawal->update([
                'status' => RebateWithdrawal::STATUS_PROCESSING,
                'reviewed_by' => $adminId ?? $withdrawal->reviewed_by,
                'processing_started_at' => now(),
                'attempts' => $withdrawal->attempts + 1,
                'exception_reason' => null,
            ]);
            ProcessRebateWithdrawal::dispatch($withdrawal->id)->afterCommit();

            return $withdrawal->refresh();
        });
    }

    public function todayUsage(int $userId): array
    {
        $query = $this->todayQuery($userId);
        $amount = '0.00';
        foreach ((clone $query)->pluck('amount') as $item) {
            $amount = RebateMoney::add($amount, (string) $item);
        }

        return [
            'count' => (clone $query)->count(),
            'amount' => $amount,
        ];
    }

    private function todayQuery(int $userId): Builder
    {
        $start = now(config('ledger.timezone', 'Asia/Shanghai'))->startOfDay();

        return RebateWithdrawal::query()
            ->where('user_id', $userId)
            ->where('read_only', false)
            ->where('status', '!=', RebateWithdrawal::STATUS_REJECTED)
            ->where('requested_at', '>=', $start)
            ->where('requested_at', '<', $start->copy()->addDay());
    }

    private function assertWritable(RebateWithdrawal $withdrawal): void
    {
        if ($withdrawal->read_only) {
            throw new LogicException('历史提现记录只读');
        }
    }
}
