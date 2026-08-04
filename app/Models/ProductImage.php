<?php

namespace App\Models;

use App\Services\Sync\ProductImageStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'external_image_id',
        'stored_file_id',
        'image_url',
        'source_url',
        'public_url',
        'local_path',
        'storage_disk',
        'optimized_at',
        'storage_key',
        'original_file_name',
        'stored_file_name',
        'content_type',
        'file_extension',
        'file_type',
        'is_public',
        'file_size_bytes',
        'width',
        'height',
        'is_primary',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'external_image_id' => 'string',
            'stored_file_id' => 'string',
            'file_size_bytes' => 'integer',
            'file_type' => 'integer',
            'is_public' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'optimized_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function resolvedUrl(): ?string
    {
        return app(ProductImageStorageService::class)->resolvedUrl($this);
    }
}
