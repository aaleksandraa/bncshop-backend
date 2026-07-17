<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplierOffer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_sku',
        'supplier_price',
        'supplier_stock',
        'is_selected_price_source',
    ];

    protected function casts(): array
    {
        return [
            'supplier_price' => 'decimal:2',
            'supplier_stock' => 'integer',
            'is_selected_price_source' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
