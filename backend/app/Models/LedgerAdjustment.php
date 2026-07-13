<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerAdjustment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_EXCEPTION = 'exception';

    public const OP_INCREMENT = 'increment';

    public const OP_DECREMENT = 'decrement';

    protected $fillable = [
        'ledger_no',
        'idempotency_key',
        'sub2api_user_id',
        'sub2api_source_id',
        'sub2api_user_email',
        'operation',
        'amount',
        'cash_amount',
        'gift_quota_amount',
        'before_balance',
        'after_balance',
        'status',
        'adjust_reason',
        'admin_notes',
        'sub2api_notes',
        'exception_reason',
        'sub2api_request',
        'sub2api_response',
        'confirm_response',
        'created_by',
        'called_at',
        'confirmed_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'gift_quota_amount' => 'decimal:2',
            'before_balance' => 'decimal:2',
            'after_balance' => 'decimal:2',
            'sub2api_request' => 'array',
            'sub2api_response' => 'array',
            'confirm_response' => 'array',
            'called_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
