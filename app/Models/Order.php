<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'tracking_token',
        'user_id',
        'customer_id',
        'status',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'city',
        'postal_code',
        'company_name',
        'jib',
        'pdv_number',
        'notes',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'total',
        'shipping_method',
        'shipping_rule_snapshot',
        'coupon_id',
        'payment_method',
        'items_count',
        'points_earned',
        'points_redeemed',
        'loyalty_discount_amount',
        'loyalty_reward_id',
        'loyalty_redemption_id',
        'terms_accepted_at',
        'create_account_requested',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_rule_snapshot' => 'array',
            'items_count' => 'integer',
            'points_earned' => 'integer',
            'points_redeemed' => 'integer',
            'loyalty_discount_amount' => 'decimal:2',
            'terms_accepted_at' => 'datetime',
            'create_account_requested' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function loyaltyReward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function loyaltyRedemption(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRedemption::class, 'loyalty_redemption_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
