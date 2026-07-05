<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
