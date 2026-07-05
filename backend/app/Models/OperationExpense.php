<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationExpense extends Model
{
    protected $fillable = [
        'expense_no',
        'category',
        'amount',
        'paid_at',
        'remark',
        'content_html',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
