<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Concerns\SanitizesStaleTemporaryUploads;
use App\Filament\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use App\Services\Catalog\ManufacturerLogoDownloader;
use App\Services\Catalog\ProductReadCache;
use Filament\Actions;
use Filament\Notifications\Notification;
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
            Actions\Action::make('downloadLogo')
                ->label('Povuci logo sa URL-a')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->logo_url))
                ->action(function (ManufacturerLogoDownloader $downloader): void {
                    /** @var Manufacturer $record */
                    $record = $this->getRecord();
                    $result = $downloader->downloadOne($record, force: true);

                    if ($result === true) {
                        $this->refreshFormData(['logo_path', 'logo_url']);
                        app(ProductReadCache::class)->flushManufacturers();

                        Notification::make()
                            ->title('Logo preuzet')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Preuzimanje nije uspjelo')
                        ->body('Provjerite da li je eksterni URL validan i dostupan.')
                        ->danger()
                        ->send();
                }),
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
