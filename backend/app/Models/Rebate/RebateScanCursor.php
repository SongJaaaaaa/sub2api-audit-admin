<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;

class RebateScanCursor extends Model
{
    protected $table = 'rebate_scan_cursors';

    protected $fillable = [
        'source_type',
        'cursor_value',
        'cursor_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'cursor_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
