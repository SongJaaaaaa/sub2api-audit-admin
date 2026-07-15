<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationExpense extends Model
{
    protected $fillable = [
        'expense_no',
        'category',
        'amount',
        'paid_at',
        'remark',
        'content_html',
        'profit_eligible',
        'profit_settlement_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'profit_eligible' => 'boolean',
            'profit_settlement_id' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
