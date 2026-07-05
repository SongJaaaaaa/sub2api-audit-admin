<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationBatch extends Model
{
    public const STATUS_BALANCED = 'balanced';

    public const STATUS_DIFF = 'diff';

    protected $fillable = [
        'batch_no',
        'biz_date',
        'cash_total',
        'quota_total',
        'gift_total',
        'sub2api_delta_total',
        'diff_amount',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'biz_date' => 'date',
            'cash_total' => 'decimal:2',
            'quota_total' => 'decimal:2',
            'gift_total' => 'decimal:2',
            'sub2api_delta_total' => 'decimal:2',
            'diff_amount' => 'decimal:2',
        ];
    }
}
