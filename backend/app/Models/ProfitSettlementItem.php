<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitSettlementItem extends Model
{
    public const TYPE_INCOME = 'cash_entry';

    public const TYPE_EXPENSE = 'operation_expense';

    protected $fillable = [
        'item_type',
        'item_id',
        'biz_date',
        'owner_admin_id',
        'owner_name',
        'reference_no',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'biz_date' => 'date',
            'item_id' => 'integer',
            'owner_admin_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ProfitSettlement::class, 'profit_settlement_id');
    }
}
