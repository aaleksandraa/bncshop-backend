<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'external_product_id',
        'product_name',
        'sku',
        'barcode',
        'brand_name',
        'category_path',
        'unit_price',
        'discount_amount',
        'final_price',
        'quantity',
        'line_total',
        'supplier_sku',
        'supplier_name',
        'attributes_snapshot',
        'discount_snapshot',
        'discount_id',
    ];

    protected function casts(): array
    {
        return [
            'external_product_id' => 'string',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'attributes_snapshot' => 'array',
            'discount_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function displayCode(): string
    {
        $code = $this->sku
            ?: $this->supplier_sku
            ?: $this->barcode;

        if (filled($code)) {
            return (string) $code;
        }

        if ($this->relationLoaded('product') || $this->product_id !== null) {
            $product = $this->product;

            if ($product !== null) {
                $code = $product->sku ?: $product->eline_sifra ?: $product->barcode;

                if (filled($code)) {
                    return (string) $code;
                }
            }
        }

        return '';
    }
}
