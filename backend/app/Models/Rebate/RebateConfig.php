<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;

class RebateConfig extends Model
{
    protected $table = 'rebate_configs';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'milestone_amount',
        'milestone_reward_amount',
        'milestone_max_times',
        'stage_amount',
        'stage_reward_amount',
        'withdraw_min_amount',
        'withdraw_daily_limit',
        'withdraw_daily_amount_limit',
        'withdraw_to_api_quota_rate',
        'native_recharge_enabled',
        'redeem_enabled',
        'admin_adjust_enabled',
        'rebate_cutover_at',
    ];

    protected function casts(): array
    {
        return [
            'milestone_amount' => 'decimal:2',
            'milestone_reward_amount' => 'decimal:2',
            'milestone_max_times' => 'integer',
            'stage_amount' => 'decimal:2',
            'stage_reward_amount' => 'decimal:2',
            'withdraw_min_amount' => 'decimal:2',
            'withdraw_daily_limit' => 'integer',
            'withdraw_daily_amount_limit' => 'decimal:2',
            'withdraw_to_api_quota_rate' => 'decimal:4',
            'native_recharge_enabled' => 'boolean',
            'redeem_enabled' => 'boolean',
            'admin_adjust_enabled' => 'boolean',
            'rebate_cutover_at' => 'datetime',
        ];
    }
}
