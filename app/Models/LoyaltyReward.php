<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'points_required',
        'reward_value',
        'product_id',
        'apply_to',
        'is_active',
        'starts_at',
        'ends_at',
        'sort_order',
        'max_uses_per_customer',
        'total_max_uses',
        'times_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'points_required' => 'integer',
            'reward_value' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
            'max_uses_per_customer' => 'integer',
            'total_max_uses' => 'integer',
            'times_redeemed' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->total_max_uses !== null && $this->times_redeemed >= $this->total_max_uses) {
            return false;
        }

        return true;
    }
}
