<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlxListingRegistry extends Model
{
    public const SYNC_MODE_LEGACY = 'legacy';

    public const SYNC_MODE_MANAGED = 'managed';

    protected $table = 'olx_listing_registry';

    protected $primaryKey = 'olx_listing_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'olx_listing_id',
        'product_id',
        'sku_number',
        'title',
        'category_id',
        'state',
        'status',
        'sync_mode',
        'match_method',
        'imported_at',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'olx_listing_id' => 'integer',
            'category_id' => 'integer',
            'imported_at' => 'datetime',
            'matched_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLegacy(): bool
    {
        return $this->sync_mode === self::SYNC_MODE_LEGACY;
    }
}
