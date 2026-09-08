<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnanasProductMapping extends Model
{
    public const LOCAL_DISABLED = 'DISABLED';

    public const LOCAL_NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    public const LOCAL_READY = 'READY';

    public const LOCAL_QUEUED = 'QUEUED';

    public const LOCAL_SUBMITTED = 'SUBMITTED';

    public const LOCAL_PENDING_ONBOARDING = 'PENDING_ONBOARDING';

    public const LOCAL_LINKED = 'LINKED';

    public const LOCAL_FAILED = 'FAILED';

    protected $fillable = [
        'product_id',
        'export_enabled',
        'ean',
        'sku',
        'external_id',
        'ananas_product_id',
        'merchant_inventory_id',
        'ananas_code',
        'group_id',
        'remote_status',
        'local_status',
        'ean_exists_on_ananas',
        'last_progress_id',
        'payload_hash',
        'stock_hash',
        'price_hash',
        'last_submitted_at',
        'last_success_at',
        'last_error_at',
        'last_error',
        'remote_modified_at',
    ];

    protected function casts(): array
    {
        return [
            'export_enabled' => 'boolean',
            'ean_exists_on_ananas' => 'boolean',
            'last_submitted_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'remote_modified_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
