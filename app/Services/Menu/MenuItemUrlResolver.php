<?php

namespace App\Services\Menu;

use App\Models\MenuItem;

class MenuItemUrlResolver
{
    public function resolve(MenuItem $item): ?string
    {
        return match ($item->type) {
            MenuItem::TYPE_CATEGORY => $item->category && $item->category->status === 'active'
                ? '/kategorija/'.$item->category->full_slug
                : null,
            MenuItem::TYPE_PAGE => $item->cmsPage
                ? '/'.$item->cmsPage->slug
                : null,
            MenuItem::TYPE_CUSTOM_LINK => $item->url,
            default => null,
        };
    }
}
