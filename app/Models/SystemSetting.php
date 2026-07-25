<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /** Internal cache blobs — never expose via storefront settings/layout APIs. */
    public const PUBLIC_EXCLUDED_KEYS = [
        'sitemap_cache',
    ];

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @param  Builder<SystemSetting>  $query
     * @return Builder<SystemSetting>
     */
    public function scopePublicFacing(Builder $query): Builder
    {
        return $query
            ->whereIn('group', ['shop', 'checkout', 'seo'])
            ->whereNotIn('key', self::PUBLIC_EXCLUDED_KEYS);
    }
}
