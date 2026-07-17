<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'external_supplier_id',
        'name',
        'display_name',
        'code',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'external_supplier_id' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function productOffers(): HasMany
    {
        return $this->hasMany(ProductSupplierOffer::class);
    }

    public function marginRules(): HasMany
    {
        return $this->hasMany(SupplierCategoryMarginRule::class);
    }

    public function preferredProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'preferred_supplier_id');
    }

    public function label(): string
    {
        return $this->display_name ?: $this->name;
    }
}
