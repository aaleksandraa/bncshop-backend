<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Concerns\SanitizesStaleTemporaryUploads;
use App\Filament\Resources\ManufacturerResource;
use App\Services\Catalog\ProductReadCache;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManufacturer extends EditRecord
{
    use SanitizesStaleTemporaryUploads;

    protected static string $resource = ManufacturerResource::class;

    protected function beforeValidate(): void
    {
        if (blank($this->getRecord()->logo_path)) {
            $this->reconcileTemporaryUploadState(
                statePath: 'data.logo_path',
                orphanDirectory: 'manufacturers/logos',
            );

            return;
        }

        $this->reconcileTemporaryUploadState(statePath: 'data.logo_path');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $cache = app(ProductReadCache::class);
        $cache->flushManufacturers();
        $cache->flushProducts();
    }
}
