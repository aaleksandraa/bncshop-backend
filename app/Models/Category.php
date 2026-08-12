<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Services\Catalog\CategoryFilterLayoutService;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'external_category_id',
        'parent_id',
        'name',
        'display_name',
        'full_slug',
        'description',
        'short_description',
        'external_id',
        'external_parent_id',
        'depth',
        'path',
        'margin_id',
        'margin_name',
        'margin_percentage',
        'margin_locked',
        'olx_id',
        'olx_name',
        'system',
        'pending_parent',
        'status',
        'image_url',
        'icon_url',
        'filter_price_enabled',
        'filter_brand_enabled',
        'filter_in_stock_enabled',
        'filter_on_sale_enabled',
        'filter_is_new_enabled',
        'filter_is_refurbished_enabled',
        'filter_layout',
    ];

    protected function casts(): array
    {
        return [
            'external_category_id' => 'string',
            'external_parent_id' => 'string',
            'margin_id' => 'string',
            'depth' => 'integer',
            'margin_percentage' => 'decimal:2',
            'margin_locked' => 'boolean',
            'olx_id' => 'integer',
            'system' => 'boolean',
            'pending_parent' => 'boolean',
            'filter_price_enabled' => 'boolean',
            'filter_brand_enabled' => 'boolean',
            'filter_in_stock_enabled' => 'boolean',
            'filter_on_sale_enabled' => 'boolean',
            'filter_is_new_enabled' => 'boolean',
            'filter_is_refurbished_enabled' => 'boolean',
            'filter_layout' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function seo(): HasOne
    {
        return $this->hasOne(CategorySeo::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function marginRule(): HasOne
    {
        return $this->hasOne(CategoryMarginRule::class);
    }

    public function attributeMappings(): HasMany
    {
        return $this->hasMany(AttributeCategoryMapping::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function shippingRules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }

    public function seoOverride(): MorphOne
    {
        return $this->morphOne(SeoOverride::class, 'seoable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForAdminSelect(Builder $query): Builder
    {
        return $query
            ->select([
                'id',
                'name',
                'display_name',
                'depth',
                'path',
            ])
            ->orderBy('path');
    }

    public function publicName(): string
    {
        $displayName = trim((string) ($this->display_name ?? ''));

        return $displayName !== '' ? $displayName : $this->name;
    }

    public function hasCompleteSeo(): bool
    {
        $seo = $this->seo;

        if ($seo === null) {
            return false;
        }

        return filled($seo->meta_title)
            && filled($seo->meta_description)
            && (filled($this->short_description) || filled($seo->intro_text));
    }

    /**
     * @return array<string, bool>
     */
    public function filterConfig(): array
    {
        return app(CategoryFilterLayoutService::class)
            ->configFromLayout(
                app(CategoryFilterLayoutService::class)->resolveLayout($this),
            );
    }
}
