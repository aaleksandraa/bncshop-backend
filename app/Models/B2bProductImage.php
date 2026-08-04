<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use App\Services\Media\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bProductImage extends Model
{
    protected $fillable = [
        'b2b_product_id',
        'path',
        'storage_disk',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'optimized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (B2bProductImage $image): void {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                app(MediaStorage::class)->deleteFromAnyDisk($image->path, $image->storage_disk);
            }
        });

        static::saving(function (B2bProductImage $image): void {
            if ($image->isDirty('path') && filled($image->path) && ! str_starts_with($image->path, 'http')) {
                $image->storage_disk = app(MediaStorage::class)->diskName();
                $image->optimized_at = now();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(B2bProduct::class, 'b2b_product_id');
    }

    public function url(): string
    {
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return PublicStorageUrl::absoluteFromResolved(PublicStorageUrl::url($this->path)) ?? $this->path;
    }
}
