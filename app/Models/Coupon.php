<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_cart_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
        'applicable_to',
        'single_use_per_customer',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_cart_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'applicable_to' => 'array',
            'single_use_per_customer' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
