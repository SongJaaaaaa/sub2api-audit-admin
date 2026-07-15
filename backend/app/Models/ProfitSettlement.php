<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitSettlement extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'batch_no',
        'start_date',
        'end_date',
        'income_total',
        'expense_total',
        'profit_total',
        'income_count',
        'expense_count',
        'status',
        'created_by',
        'reversed_by',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'income_total' => 'decimal:2',
            'expense_total' => 'decimal:2',
            'profit_total' => 'decimal:2',
            'income_count' => 'integer',
            'expense_count' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProfitSettlementItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reversed_by');
    }
}
