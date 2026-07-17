<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bCart extends Model
{
    protected $fillable = [
        'b2b_customer_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(B2bCustomer::class, 'b2b_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B2bCartItem::class, 'b2b_cart_id');
    }
}
