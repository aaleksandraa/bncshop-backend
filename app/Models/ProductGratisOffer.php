<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGratisOffer extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'product_id',
        'sort_order',
        'type',
        'gift_product_id',
        'gift_quantity_per_parent',
        'title',
        'description',
        'image_path',
        'starts_at',
        'ends_at',
        'until_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'gift_quantity_per_parent' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'until_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function giftProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    public function isProductType(): bool
    {
        return $this->type === self::TYPE_PRODUCT;
    }

    public function isTextType(): bool
    {
        return $this->type === self::TYPE_TEXT;
    }
}
