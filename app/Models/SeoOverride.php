<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoOverride extends Model
{
    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'meta_title',
        'meta_description',
        'og_image_url',
        'canonical',
        'robots',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    public function setRobotsAttribute(?string $value): void
    {
        $this->attributes['robots'] = $value ?? 'index,follow';
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
