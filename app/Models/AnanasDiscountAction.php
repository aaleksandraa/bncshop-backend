<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasDiscountAction extends Model
{
    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'ananas_product_mapping_id',
        'merchant_inventory_id',
        'ananas_discount_id',
        'discount_type',
        'discount_price',
        'currency',
        'date_from',
        'date_to',
        'local_status',
        'last_error',
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'discount_price' => 'decimal:2',
            'date_from' => 'date',
            'date_to' => 'date',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function productMapping(): BelongsTo
    {
        return $this->belongsTo(AnanasProductMapping::class, 'ananas_product_mapping_id');
    }
}
