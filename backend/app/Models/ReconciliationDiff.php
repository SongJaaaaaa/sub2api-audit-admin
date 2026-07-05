<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationDiff extends Model
{
    protected $fillable = [
        'reconciliation_batch_id',
        'type',
        'title',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
