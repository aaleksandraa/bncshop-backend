<?php

namespace App\Filament\Concerns;

use App\Filament\Forms\CategoryMappingSelect;
use App\Services\Catalog\ProductBulkService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Database\Eloquent\Collection;

trait HasProductBulkActions
{
    /**
     * @return array<int, Tables\Actions\BulkAction>
     */
    protected static function productBulkActions(): array
    {
        return [
            static::makeReassignCategoryBulkAction(),
            static::makeUpdateStatusBulkAction(),
            static::makeUpdateVisibilityBulkAction(),
            static::makeArchiveBulkAction(),
        ];
    }

    protected static function makeReassignCategoryBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('reassignCategory')
            ->label('Promijeni kategoriju')
            ->icon('heroicon-o-folder')
            ->form([
                CategoryMappingSelect::make('category_id')
                    ->label('Nova kategorija')
                    ->required(),
            ])
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $updated = app(ProductBulkService::class)->reassignCategory(
                    $records,
                    (int) $data['category_id'],
                );

                Notification::make()
                    ->title('Kategorija ažurirana')
                    ->body("Ažurirano {$updated} proizvoda.")
                    ->success()
                    ->send();
            });
    }

    protected static function makeUpdateStatusBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('updateStatus')
            ->label('Promijeni status')
            ->icon('heroicon-o-adjustments-horizontal')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivan',
                        'inactive' => 'Neaktivan',
                        'archived' => 'Arhiviran',
                    ])
                    ->required(),
            ])
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $updated = app(ProductBulkService::class)->updateStatus(
                    $records,
                    (string) $data['status'],
                );

                Notification::make()
                    ->title('Status ažuriran')
                    ->body("Ažurirano {$updated} proizvoda.")
                    ->success()
                    ->send();
            });
    }

    protected static function makeUpdateVisibilityBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('updateVisibility')
            ->label('Vidljivost')
            ->icon('heroicon-o-eye')
            ->form([
                Forms\Components\Toggle::make('is_public')
                    ->label('Javno vidljivo')
                    ->default(true),
            ])
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $updated = app(ProductBulkService::class)->updateVisibility(
                    $records,
                    (bool) $data['is_public'],
                );

                $label = $data['is_public'] ? 'prikazano' : 'sakriveno';

                Notification::make()
                    ->title('Vidljivost ažurirana')
                    ->body("{$updated} proizvoda je {$label}.")
                    ->success()
                    ->send();
            });
    }

    protected static function makeArchiveBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('archive')
            ->label('Arhiviraj')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $updated = app(ProductBulkService::class)->updateStatus($records, 'archived');

                Notification::make()
                    ->title('Proizvodi arhivirani')
                    ->body("Arhivirano {$updated} proizvoda.")
                    ->success()
                    ->send();
            });
    }
}
