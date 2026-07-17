<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderItem extends Model
{
    protected $fillable = [
        'b2b_order_id',
        'b2b_product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_regular_price',
        'unit_final_price',
        'line_total',
        'customer_discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_regular_price' => 'decimal:2',
            'unit_final_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'customer_discount_percent' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(B2bProduct::class, 'b2b_product_id');
    }
}
