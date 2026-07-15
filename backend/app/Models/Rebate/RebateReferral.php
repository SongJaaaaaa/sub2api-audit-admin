<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RebateReferral extends Model
{
    protected $table = 'rebate_referrals';

    protected $fillable = [
        'user_id',
        'parent_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RebateUser::class, 'parent_user_id');
    }
}
