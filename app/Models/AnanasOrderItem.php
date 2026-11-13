<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasOrderItem extends Model
{
    protected $fillable = [
        'ananas_order_id',
        'ananas_line_id',
        'product_id',
        'ananas_product_mapping_id',
        'merchant_inventory_id',
        'product_ean',
        'product_sku',
        'product_name',
        'quantity_ordered',
        'quantity_confirmed',
        'quantity_packed',
        'committed_quantity',
        'unit_price',
        'line_total',
        'vat',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'vat' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AnanasOrder::class, 'ananas_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(AnanasProductMapping::class, 'ananas_product_mapping_id');
    }

    public function confirmableInventoryId(): int
    {
        if ($this->merchant_inventory_id !== null && (int) $this->merchant_inventory_id > 0) {
            return (int) $this->merchant_inventory_id;
        }

        return (int) ($this->mapping?->inventoryId() ?? 0);
    }
}
