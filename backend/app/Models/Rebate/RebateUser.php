<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class RebateUser extends Authenticatable
{
    use HasApiTokens;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'rebate_users';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'username',
        'email',
        'status',
        'aff_code',
        'last_synced_at',
        'legacy_source',
        'legacy_source_id',
        'source_hash',
        'read_only',
    ];

    public function referral(): HasOne
    {
        return $this->hasOne(RebateReferral::class, 'user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(RebateReferral::class, 'parent_user_id');
    }

    public function balance(): HasOne
    {
        return $this->hasOne(RebateBalance::class, 'user_id');
    }

    public function progress(): HasOne
    {
        return $this->hasOne(RebateProgress::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'read_only' => 'boolean',
        ];
    }
}
