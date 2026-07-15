<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RebateBalanceEntry extends Model
{
    public const ACTION_CREDIT = 'credit';

    public const ACTION_FREEZE = 'freeze';

    public const ACTION_UNFREEZE = 'unfreeze';

    public const ACTION_WITHDRAW = 'withdraw';

    public const UPDATED_AT = null;

    protected $table = 'rebate_balance_entries';

    protected $fillable = [
        'balance_id',
        'user_id',
        'action',
        'amount',
        'available_before',
        'available_after',
        'frozen_before',
        'frozen_after',
        'withdrawn_before',
        'withdrawn_after',
        'business_type',
        'business_key',
        'note',
        'meta',
        'legacy_source',
        'legacy_source_id',
        'source_hash',
        'read_only',
    ];

    public function balance(): BelongsTo
    {
        return $this->belongsTo(RebateBalance::class, 'balance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('返利余额流水不可修改'));
        static::deleting(fn () => throw new LogicException('返利余额流水不可删除'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_before' => 'decimal:2',
            'available_after' => 'decimal:2',
            'frozen_before' => 'decimal:2',
            'frozen_after' => 'decimal:2',
            'withdrawn_before' => 'decimal:2',
            'withdrawn_after' => 'decimal:2',
            'meta' => 'array',
            'read_only' => 'boolean',
        ];
    }
}
