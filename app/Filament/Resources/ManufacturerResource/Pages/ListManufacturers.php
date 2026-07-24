<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use App\Services\Catalog\ManufacturerLogoDownloader;
use App\Services\Catalog\ProductReadCache;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListManufacturers extends ListRecords
{
    protected static string $resource = ManufacturerResource::class;

    public function reorderTable(array $order): void
    {
        parent::reorderTable($order);
        app(ProductReadCache::class)->flushManufacturers();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadLogos')
                ->label('Povuci logotipe')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Povuci logotipe sa A1 brend stranice')
                ->modalDescription('Pronalazi logotipe na a1team.ba/brendovi i preuzima ih lokalno (do 100 po pokretanju).')
                ->action(function (
                    ManufacturerLogoDownloader $downloader,
                    ProductReadCache $cache,
                ): void {
                    $result = $downloader->downloadMissing(limit: 100, force: false);
                    $cache->flushManufacturers();

                    Notification::make()
                        ->title('Preuzimanje logotipa završeno')
                        ->body(sprintf(
                            'Pronađeno URL: %d, preuzeto: %d, preskočeno: %d, neuspješno: %d, bez loga: %d',
                            $result['resolved'],
                            $result['downloaded'],
                            $result['skipped'],
                            $result['failed'],
                            $result['unmatched'],
                        ))
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
