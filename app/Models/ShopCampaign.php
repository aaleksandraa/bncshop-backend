<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShopCampaign extends Model
{
    use HasFactory;
    public const TARGETING_CATEGORIES = 'categories';

    public const TARGETING_PRODUCTS = 'products';

    protected $fillable = [
        'name',
        'slug',
        'badge_path',
        'badge_alt',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'targeting_mode',
        'include_subcategories',
        'has_landing_page',
        'page_title',
        'page_description',
        'hero_image_path',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'include_subcategories' => 'boolean',
            'has_landing_page' => 'boolean',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'shop_campaign_category');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_campaign_product');
    }

    public function excludedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_campaign_excluded_product');
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function publicPageTitle(): string
    {
        return $this->page_title ?: $this->name;
    }
}
