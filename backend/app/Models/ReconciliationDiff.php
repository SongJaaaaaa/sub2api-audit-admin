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
        'local_adjustment_id',
        'remote_event_id',
        'sub2api_user_id',
        'direction',
        'local_amount',
        'remote_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'local_amount' => 'decimal:8',
            'remote_amount' => 'decimal:8',
        ];
    }
}
