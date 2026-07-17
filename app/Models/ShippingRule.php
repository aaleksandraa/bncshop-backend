<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRule extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'type',
        'category_id',
        'fixed_fee',
        'free_threshold',
        'pickup_enabled',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'fixed_fee' => 'decimal:2',
            'free_threshold' => 'decimal:2',
            'pickup_enabled' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
