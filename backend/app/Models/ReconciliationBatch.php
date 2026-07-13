<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationBatch extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'batch_no',
        'biz_date',
        'period_start',
        'period_end',
        'cash_total',
        'quota_total',
        'gift_total',
        'sub2api_delta_total',
        'diff_amount',
        'local_success_count',
        'local_adjustment_net',
        'remote_matched_count',
        'remote_matched_net',
        'external_count',
        'external_net',
        'audit_orphan_count',
        'audit_orphan_net',
        'issue_count',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'biz_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'cash_total' => 'decimal:2',
            'quota_total' => 'decimal:2',
            'gift_total' => 'decimal:2',
            'sub2api_delta_total' => 'decimal:2',
            'diff_amount' => 'decimal:2',
            'local_adjustment_net' => 'decimal:8',
            'remote_matched_net' => 'decimal:8',
            'external_net' => 'decimal:8',
            'audit_orphan_net' => 'decimal:8',
        ];
    }

    public function diffs(): HasMany
    {
        return $this->hasMany(ReconciliationDiff::class);
    }
}
