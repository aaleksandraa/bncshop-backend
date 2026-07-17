<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'external_product_id',
        'import_source',
        'manufacturer_id',
        'category_id',
        'api_source_id',
        'preferred_supplier_id',
        'name',
        'slug',
        'sku',
        'eline_sifra',
        'eline_feed_hash',
        'barcode',
        'description',
        'short_description',
        'is_gaming',
        'is_public',
        'is_new',
        'is_refurbished',
        'status',
        'margin_percentage',
        'api_price',
        'api_final_price',
        'regular_price',
        'display_price',
        'on_sale',
        'api_rebate',
        'api_rebate_valid_until',
        'api_rebate_type',
        'api_stock',
        'reserved_stock',
        'available_stock',
        'manual_stock_override',
        'stock_status',
        'allow_backorder',
        'price_locked',
        'manual_price',
        'default_image_id',
        'api_default_image_id',
        'api_default_image_url',
        'api_views_count',
        'first_imported_at',
        'sync_status',
        'marked_missing_at',
        'olx_listing_id',
        'olx_synced_at',
        'olx_last_error',
        'olx_export_hash',
        'olx_listing_status',
        'olx_export_enabled',
        'olx_managed',
        'olx_image_map',
    ];

    protected function casts(): array
    {
        return [
            'external_product_id' => 'string',
            'is_gaming' => 'boolean',
            'is_public' => 'boolean',
            'is_new' => 'boolean',
            'is_refurbished' => 'boolean',
            'margin_percentage' => 'decimal:2',
            'api_price' => 'decimal:2',
            'api_final_price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'display_price' => 'decimal:2',
            'on_sale' => 'boolean',
            'api_rebate' => 'decimal:2',
            'api_rebate_valid_until' => 'datetime',
            'api_rebate_type' => 'integer',
            'api_stock' => 'integer',
            'reserved_stock' => 'integer',
            'available_stock' => 'integer',
            'manual_stock_override' => 'integer',
            'allow_backorder' => 'boolean',
            'price_locked' => 'boolean',
            'manual_price' => 'decimal:2',
            'default_image_id' => 'integer',
            'api_default_image_id' => 'string',
            'api_views_count' => 'integer',
            'first_imported_at' => 'datetime',
            'marked_missing_at' => 'datetime',
            'olx_synced_at' => 'datetime',
            'olx_export_enabled' => 'boolean',
            'olx_managed' => 'boolean',
            'olx_image_map' => 'array',
        ];
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing([
            'manufacturer:id,slug',
            'category:id,full_slug',
            'attributeValues.attributeDefinition.canonicalDefinition',
        ]);

        $filterAttributes = [];

        foreach ($this->attributeValues as $value) {
            if ($value->normalized_value === null || $value->normalized_value === '') {
                continue;
            }

            $definitionId = (string) ($value->attributeDefinition?->resolveCanonicalId() ?? $value->attribute_definition_id);
            $filterAttributes[$definitionId] = (string) $value->normalized_value;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'short_description' => $this->short_description,
            'manufacturer_id' => $this->manufacturer_id,
            'manufacturer_slug' => $this->manufacturer?->slug,
            'category_id' => $this->category_id,
            'category_full_slug' => $this->category?->full_slug,
            'is_public' => $this->is_public,
            'status' => $this->status,
            'display_price' => $this->display_price,
            'available_stock' => $this->available_stock,
            'is_gaming' => $this->is_gaming,
            'is_new' => $this->is_new,
            'is_refurbished' => $this->is_refurbished,
            'import_source' => $this->import_source,
            'on_sale' => (bool) $this->on_sale,
            'filter_attributes' => $filterAttributes,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_public && $this->status === 'active';
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(ApiSource::class);
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function defaultImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'default_image_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('status', 'active')
            ->orderBy('sort_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function supplierOffers(): HasMany
    {
        return $this->hasMany(ProductSupplierOffer::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function syncLocks(): HasMany
    {
        return $this->hasMany(ProductSyncLock::class);
    }

    public function syncDiffLogs(): HasMany
    {
        return $this->hasMany(SyncDiffLog::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function seoOverride(): MorphOne
    {
        return $this->morphOne(SeoOverride::class, 'seoable');
    }

    public function syncStockStatus(): void
    {
        if ($this->available_stock <= 0) {
            $this->stock_status = 'out_of_stock';

            return;
        }

        $this->stock_status = $this->import_source === 'eline' ? 'store_available' : 'in_stock';
    }

    public function codeForOrder(?string $selectedSupplierSku = null): ?string
    {
        $code = $this->sku
            ?: $selectedSupplierSku
            ?: $this->eline_sifra
            ?: $this->barcode;

        return filled($code) ? (string) $code : null;
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFromEline(Builder $query): Builder
    {
        return $query->where('import_source', 'eline');
    }
}
