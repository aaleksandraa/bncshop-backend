<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bProduct extends Model
{
    protected $fillable = [
        'b2b_category_id',
        'name',
        'slug',
        'description',
        'regular_price',
        'sale_price',
        'exclude_customer_discount',
        'stock_quantity',
        'sku',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'exclude_customer_discount' => 'boolean',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(B2bCategory::class, 'b2b_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(B2bProductImage::class, 'b2b_product_id')->orderBy('sort_order');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(B2bCampaign::class, 'b2b_campaign_product', 'b2b_product_id', 'b2b_campaign_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(B2bProductAttributeValue::class, 'b2b_product_id');
    }

    public function primaryImage(): ?B2bProductImage
    {
        if ($this->relationLoaded('images')) {
            return $this->images->firstWhere('is_primary', true)
                ?? $this->images->first();
        }

        return $this->images()->where('is_primary', true)->first()
            ?? $this->images()->first();
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function frontendUrl(): string
    {
        return rtrim((string) config('bnc.frontend_url'), '/').'/b2b/proizvod/'.$this->slug;
    }

    public static function catalogUrl(): string
    {
        return rtrim((string) config('bnc.frontend_url'), '/').'/b2b/katalog';
    }
}
