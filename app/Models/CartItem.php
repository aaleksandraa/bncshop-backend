<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_snapshot',
        'price_confirmed',
        'is_loyalty_reward',
        'is_gratis_gift',
        'parent_cart_item_id',
        'product_gratis_offer_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_snapshot' => 'array',
            'price_confirmed' => 'boolean',
            'is_loyalty_reward' => 'boolean',
            'is_gratis_gift' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_cart_item_id');
    }

    public function gratisGiftItems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_cart_item_id');
    }

    public function gratisOffer(): BelongsTo
    {
        return $this->belongsTo(ProductGratisOffer::class, 'product_gratis_offer_id');
    }

    public function isGratisGift(): bool
    {
        return (bool) $this->is_gratis_gift;
    }
}
