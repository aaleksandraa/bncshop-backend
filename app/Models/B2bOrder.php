<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bOrder extends Model
{
    protected $fillable = [
        'order_number',
        'b2b_customer_id',
        'status',
        'payment_method',
        'company_name',
        'company_address',
        'jib',
        'pdv_number',
        'contact_name',
        'contact_email',
        'contact_phone',
        'shipping_address',
        'notes',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'total',
        'invoice_path',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(B2bCustomer::class, 'b2b_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B2bOrderItem::class, 'b2b_order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(B2bOrderStatusHistory::class, 'b2b_order_id');
    }
}
