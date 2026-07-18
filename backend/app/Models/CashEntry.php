<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    public const DIR_IN = 'in';

    protected $fillable = [
        'entry_no',
        'ledger_adjustment_id',
        'sub2api_user_id',
        'sub2api_user_email',
        'direction',
        'cash_amount',
        'source',
        'remark',
        'profit_eligible',
        'profit_settlement_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cash_amount' => 'decimal:2',
            'profit_eligible' => 'boolean',
            'profit_settlement_id' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
