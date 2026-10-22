<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bStockMovement extends Model
{
    public const TYPE_ADMIN = 'admin';

    public const TYPE_ORDER = 'order';

    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    protected $fillable = [
        'b2b_product_id',
        'delta',
        'quantity_before',
        'quantity_after',
        'type',
        'summary',
        'b2b_order_id',
        'b2b_customer_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(B2bProduct::class, 'b2b_product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(B2bCustomer::class, 'b2b_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signedDelta(): string
    {
        return ($this->delta > 0 ? '+' : '').$this->delta;
    }
}
