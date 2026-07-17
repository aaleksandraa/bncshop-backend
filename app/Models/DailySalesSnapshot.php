<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesSnapshot extends Model
{
    protected $fillable = [
        'date',
        'revenue',
        'orders_count',
        'items_sold',
        'avg_order_value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue' => 'decimal:2',
            'orders_count' => 'integer',
            'items_sold' => 'integer',
            'avg_order_value' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
