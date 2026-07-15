<?php

namespace App\Services\Rebate;

use App\Models\Rebate\RebateBalance;
use App\Models\Rebate\RebateBalanceEntry;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class BalanceService
{
    public function get(int $userId): RebateBalance
    {
        return RebateBalance::query()->createOrFirst(
            ['user_id' => $userId],
            $this->zeroBalance(),
        );
    }

    public function lockForUpdate(int $userId): RebateBalance
    {
        $this->get($userId);

        return RebateBalance::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function credit(
        int $userId,
        int|string $amount,
        string $businessType,
        string $businessKey,
        ?string $note = null,
        array $meta = [],
    ): RebateBalance {
        return $this->apply(
            $userId,
            $amount,
            RebateBalanceEntry::ACTION_CREDIT,
            $businessType,
            $businessKey,
            $note,
            $meta,
        );
    }

    public function freeze(
        int $userId,
        int|string $amount,
        string $businessType,
        string $businessKey,
        ?string $note = null,
        array $meta = [],
    ): RebateBalance {
        return $this->apply(
            $userId,
            $amount,
            RebateBalanceEntry::ACTION_FREEZE,
            $businessType,
            $businessKey,
            $note,
            $meta,
        );
    }

    public function unfreeze(
        int $userId,
        int|string $amount,
        string $businessType,
        string $businessKey,
        ?string $note = null,
        array $meta = [],
    ): RebateBalance {
        return $this->apply(
            $userId,
            $amount,
            RebateBalanceEntry::ACTION_UNFREEZE,
            $businessType,
            $businessKey,
            $note,
            $meta,
        );
    }

    public function completeWithdrawal(
        int $userId,
        int|string $amount,
        string $businessType,
        string $businessKey,
        ?string $note = null,
        array $meta = [],
    ): RebateBalance {
        return $this->apply(
            $userId,
            $amount,
            RebateBalanceEntry::ACTION_WITHDRAW,
            $businessType,
            $businessKey,
            $note,
            $meta,
        );
    }

    private function apply(
        int $userId,
        int|string $value,
        string $action,
        string $businessType,
        string $businessKey,
        ?string $note,
        array $meta,
    ): RebateBalance {
        $amount = RebateMoney::positive($value);
        if ($businessType === '' || $businessKey === '') {
            throw new LogicException('余额流水业务键不能为空');
        }

        return DB::transaction(function () use ($userId, $amount, $action, $businessType, $businessKey, $note, $meta): RebateBalance {
            $balance = $this->lockForUpdate($userId);
            $entry = RebateBalanceEntry::query()
                ->where('business_type', $businessType)
                ->where('business_key', $businessKey)
                ->where('action', $action)
                ->first();

            if ($entry) {
                if ($entry->user_id !== $userId || RebateMoney::compare($entry->amount, $amount) !== 0) {
                    throw new LogicException('余额流水幂等键与原业务不一致');
                }

                return $balance;
            }

            $before = [
                'available' => $balance->available_amount,
                'frozen' => $balance->frozen_amount,
                'withdrawn' => $balance->withdrawn_amount,
            ];

            match ($action) {
                RebateBalanceEntry::ACTION_CREDIT => $balance->available_amount = RebateMoney::add($before['available'], $amount),
                RebateBalanceEntry::ACTION_FREEZE => $this->moveAvailableToFrozen($balance, $amount),
                RebateBalanceEntry::ACTION_UNFREEZE => $this->moveFrozenToAvailable($balance, $amount),
                RebateBalanceEntry::ACTION_WITHDRAW => $this->moveFrozenToWithdrawn($balance, $amount),
                default => throw new LogicException('未知余额动作'),
            };
            $balance->save();

            RebateBalanceEntry::query()->create([
                'balance_id' => $balance->id,
                'user_id' => $userId,
                'action' => $action,
                'amount' => $amount,
                'available_before' => $before['available'],
                'available_after' => $balance->available_amount,
                'frozen_before' => $before['frozen'],
                'frozen_after' => $balance->frozen_amount,
                'withdrawn_before' => $before['withdrawn'],
                'withdrawn_after' => $balance->withdrawn_amount,
                'business_type' => $businessType,
                'business_key' => $businessKey,
                'note' => $note,
                'meta' => $meta === [] ? null : $meta,
            ]);

            return $balance->refresh();
        });
    }

    private function moveAvailableToFrozen(RebateBalance $balance, string $amount): void
    {
        if (RebateMoney::compare($balance->available_amount, $amount) < 0) {
            throw new RuntimeException('可提现余额不足');
        }

        $balance->available_amount = RebateMoney::sub($balance->available_amount, $amount);
        $balance->frozen_amount = RebateMoney::add($balance->frozen_amount, $amount);
    }

    private function moveFrozenToAvailable(RebateBalance $balance, string $amount): void
    {
        if (RebateMoney::compare($balance->frozen_amount, $amount) < 0) {
            throw new RuntimeException('冻结余额不足');
        }

        $balance->frozen_amount = RebateMoney::sub($balance->frozen_amount, $amount);
        $balance->available_amount = RebateMoney::add($balance->available_amount, $amount);
    }

    private function moveFrozenToWithdrawn(RebateBalance $balance, string $amount): void
    {
        if (RebateMoney::compare($balance->frozen_amount, $amount) < 0) {
            throw new RuntimeException('冻结余额不足');
        }

        $balance->frozen_amount = RebateMoney::sub($balance->frozen_amount, $amount);
        $balance->withdrawn_amount = RebateMoney::add($balance->withdrawn_amount, $amount);
    }

    private function zeroBalance(): array
    {
        return [
            'available_amount' => '0.00',
            'frozen_amount' => '0.00',
            'withdrawn_amount' => '0.00',
        ];
    }
}
