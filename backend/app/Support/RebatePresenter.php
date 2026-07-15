<?php

namespace App\Support;

use App\Models\Rebate\RebateBalance;
use App\Models\Rebate\RebateConfig;
use App\Models\Rebate\RebateRecord;
use App\Models\Rebate\RebateUser;
use App\Models\Rebate\RebateWithdrawal;

class RebatePresenter
{
    public static function user(RebateUser $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'status' => $user->status,
            'invite_code' => $user->aff_code,
            'created_at' => ChinaTime::fmt($user->created_at),
        ];
    }

    public static function balance(?RebateBalance $balance): array
    {
        $available = (string) ($balance?->available_amount ?? '0.00');
        $frozen = (string) ($balance?->frozen_amount ?? '0.00');
        $withdrawn = (string) ($balance?->withdrawn_amount ?? '0.00');

        return [
            'available_amount' => $available,
            'frozen_amount' => $frozen,
            'withdrawn_amount' => $withdrawn,
            'total_rebate_amount' => bcadd(bcadd($available, $frozen, 2), $withdrawn, 2),
        ];
    }

    public static function record(RebateRecord $record): array
    {
        return [
            'id' => $record->id,
            'event_id' => $record->event_id,
            'payer_user_id' => $record->payer_user_id,
            'payer_email' => $record->payer?->email,
            'receiver_user_id' => $record->receiver_user_id,
            'receiver_email' => $record->receiver?->email,
            'type' => $record->type,
            'level' => $record->level,
            'source_amount' => (string) $record->source_amount,
            'rebate_amount' => (string) $record->rebate_amount,
            'status' => $record->status,
            'created_at' => ChinaTime::fmt($record->created_at),
        ];
    }

    public static function withdrawal(RebateWithdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'request_no' => $withdrawal->withdrawal_no,
            'user_id' => $withdrawal->user_id,
            'user_email' => $withdrawal->user?->email,
            'amount' => (string) $withdrawal->amount,
            'quota_amount' => (string) $withdrawal->quota_amount,
            'status' => $withdrawal->status,
            'reject_reason' => $withdrawal->reject_reason,
            'error_message' => $withdrawal->exception_reason,
            'reviewed_at' => ChinaTime::fmt($withdrawal->reviewed_at),
            'completed_at' => ChinaTime::fmt($withdrawal->completed_at),
            'created_at' => ChinaTime::fmt($withdrawal->requested_at),
        ];
    }

    public static function config(RebateConfig $config): array
    {
        return [
            'milestone_amount' => (string) $config->milestone_amount,
            'milestone_reward_amount' => (string) $config->milestone_reward_amount,
            'milestone_max_times' => $config->milestone_max_times,
            'stage_amount' => (string) $config->stage_amount,
            'stage_reward_amount' => (string) $config->stage_reward_amount,
            'withdraw_min_amount' => (string) $config->withdraw_min_amount,
            'withdraw_daily_limit' => $config->withdraw_daily_limit,
            'withdraw_daily_amount_limit' => (string) $config->withdraw_daily_amount_limit,
            'withdraw_to_api_quota_rate' => (string) $config->withdraw_to_api_quota_rate,
            'native_recharge_enabled' => $config->native_recharge_enabled,
            'redeem_enabled' => $config->redeem_enabled,
            'admin_adjust_enabled' => $config->admin_adjust_enabled,
            'rebate_cutover_at' => ChinaTime::fmt($config->rebate_cutover_at),
            'updated_at' => ChinaTime::fmt($config->updated_at),
        ];
    }
}
