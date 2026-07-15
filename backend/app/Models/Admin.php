<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
