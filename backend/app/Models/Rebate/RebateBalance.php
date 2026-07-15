<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RebateBalance extends Model
{
    protected $table = 'rebate_balances';

    protected $fillable = [
        'user_id',
        'available_amount',
        'frozen_amount',
        'withdrawn_amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RebateBalanceEntry::class, 'balance_id');
    }

    protected function casts(): array
    {
        return [
            'available_amount' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
            'withdrawn_amount' => 'decimal:2',
        ];
    }
}
