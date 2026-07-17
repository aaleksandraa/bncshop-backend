<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    public const TYPE_CATEGORY = 'category';

    public const TYPE_PAGE = 'page';

    public const TYPE_CUSTOM_LINK = 'custom_link';

    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'label',
        'category_id',
        'cms_page_id',
        'url',
        'open_in_new_tab',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cmsPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function resolvedLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return match ($this->type) {
            self::TYPE_CATEGORY => $this->category?->publicName() ?? 'Kategorija',
            self::TYPE_PAGE => $this->cmsPage?->title ?? 'Stranica',
            self::TYPE_CUSTOM_LINK => $this->url ?? 'Link',
            default => 'Stavka',
        };
    }

    public function resolvedUrl(): ?string
    {
        return app(\App\Services\Menu\MenuItemUrlResolver::class)->resolve($this);
    }
}
