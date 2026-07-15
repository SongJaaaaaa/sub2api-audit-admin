<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RebateEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $table = 'rebate_events';

    protected $fillable = [
        'source_type',
        'source_id',
        'user_id',
        'amount',
        'happened_at',
        'payload',
        'status',
        'attempts',
        'error',
        'processed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(RebateRecord::class, 'event_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'happened_at' => 'datetime',
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
