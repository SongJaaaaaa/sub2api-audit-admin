<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftQuotaEntry extends Model
{
    protected $fillable = [
        'entry_no',
        'ledger_adjustment_id',
        'sub2api_user_id',
        'sub2api_user_email',
        'quota_amount',
        'source',
        'remark',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quota_amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
