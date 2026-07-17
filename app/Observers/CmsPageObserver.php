<?php

namespace App\Observers;

use App\Models\CmsPage;
use App\Services\Catalog\ProductReadCache;

class CmsPageObserver
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function saved(CmsPage $page): void
    {
        $this->productReadCache->forgetPage($page->slug);
        $this->productReadCache->flushCms();
    }

    public function saving(CmsPage $page): void
    {
        if ($page->body !== null) {
            $page->body = \App\Support\SafeHtml::clean($page->body);
        }
    }

    public function deleted(CmsPage $page): void
    {
        $this->productReadCache->forgetPage($page->slug);
        $this->productReadCache->flushCms();
    }
}
