<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebateRecord extends Model
{
    public const TYPE_MILESTONE = 'milestone';

    public const TYPE_STAGE = 'stage';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $table = 'rebate_records';

    protected $fillable = [
        'event_id',
        'receiver_user_id',
        'payer_user_id',
        'level',
        'type',
        'source_amount',
        'rebate_amount',
        'trigger_count',
        'status',
        'config_snapshot',
        'remark',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(RebateEvent::class, 'event_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'receiver_user_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'payer_user_id');
    }

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'source_amount' => 'decimal:2',
            'rebate_amount' => 'decimal:2',
            'trigger_count' => 'integer',
            'config_snapshot' => 'array',
        ];
    }
}
