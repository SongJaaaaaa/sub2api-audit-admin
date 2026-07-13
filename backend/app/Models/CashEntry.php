<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    public const DIR_IN = 'in';

    public const DIR_OUT = 'out';

    protected $fillable = [
        'entry_no',
        'ledger_adjustment_id',
        'sub2api_user_id',
        'sub2api_user_email',
        'direction',
        'cash_amount',
        'source',
        'remark',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cash_amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
