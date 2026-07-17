<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bCartItem extends Model
{
    protected $fillable = [
        'b2b_cart_id',
        'b2b_product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(B2bCart::class, 'b2b_cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(B2bProduct::class, 'b2b_product_id');
    }
}
