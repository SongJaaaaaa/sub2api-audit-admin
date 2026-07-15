<?php

namespace App\Models\Rebate;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebateWithdrawal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXCEPTION = 'exception';

    protected $table = 'rebate_withdrawals';

    protected $fillable = [
        'withdrawal_no',
        'user_id',
        'amount',
        'quota_amount',
        'status',
        'remark',
        'reviewed_by',
        'reject_reason',
        'exception_reason',
        'attempts',
        'payout_reference',
        'payout_response',
        'requested_at',
        'reviewed_at',
        'processing_started_at',
        'completed_at',
        'legacy_source',
        'legacy_source_id',
        'source_hash',
        'read_only',
        'meta',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quota_amount' => 'decimal:2',
            'attempts' => 'integer',
            'payout_response' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'read_only' => 'boolean',
            'meta' => 'array',
        ];
    }
}
