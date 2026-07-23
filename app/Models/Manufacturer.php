<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_manufacturer_id',
        'name',
        'slug',
        'external_id',
        'system',
        'featured',
        'sort_order',
        'description',
        'meta_title',
        'meta_description',
        'logo_url',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'external_manufacturer_id' => 'string',
            'system' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Manufacturer $manufacturer): void {
            if ($manufacturer->logo_path) {
                Storage::disk('public')->delete($manufacturer->logo_path);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function seoOverride(): MorphOne
    {
        return $this->morphOne(SeoOverride::class, 'seoable');
    }

    public function logoUrl(): ?string
    {
        if (filled($this->logo_path)) {
            return PublicStorageUrl::url($this->logo_path);
        }

        return $this->logo_url;
    }
}
