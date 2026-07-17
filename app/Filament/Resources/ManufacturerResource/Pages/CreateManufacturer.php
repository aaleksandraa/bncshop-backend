<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use App\Services\Catalog\ProductReadCache;
use Filament\Resources\Pages\CreateRecord;

class CreateManufacturer extends CreateRecord
{
    protected static string $resource = ManufacturerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['system'] = false;
        $data['external_manufacturer_id'] = $data['external_manufacturer_id'] ?? (string) \Illuminate\Support\Str::uuid();

        return $data;
    }

    protected function afterCreate(): void
    {
        $cache = app(ProductReadCache::class);
        $cache->flushManufacturers();
        $cache->flushProducts();
    }
}
