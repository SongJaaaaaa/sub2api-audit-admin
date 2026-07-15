<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebateProgress extends Model
{
    protected $table = 'rebate_progress';

    protected $fillable = [
        'user_id',
        'total_recharge_amount',
        'milestone_times',
        'stage_times',
        'last_event_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(RebateEvent::class, 'last_event_id');
    }

    protected function casts(): array
    {
        return [
            'total_recharge_amount' => 'decimal:2',
            'milestone_times' => 'integer',
            'stage_times' => 'integer',
        ];
    }
}
