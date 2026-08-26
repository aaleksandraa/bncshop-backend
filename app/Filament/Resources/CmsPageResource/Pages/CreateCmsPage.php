<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Resources\CmsPageResource;
use App\Services\Catalog\ProductReadCache;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function afterCreate(): void
    {
        app(ProductReadCache::class)->flushProducts();
    }
}
