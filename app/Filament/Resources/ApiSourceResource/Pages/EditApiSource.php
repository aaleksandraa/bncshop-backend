<?php

namespace App\Filament\Resources\ApiSourceResource\Pages;

use App\Filament\Resources\ApiSourceResource;
use App\Services\Eline\ElineSyncOrchestrator;
use App\Services\Sync\A1SyncSettings;
use App\Services\Sync\IntegrationApiClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditApiSource extends EditRecord
{
    protected static string $resource = ApiSourceResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->record?->target_system_code === 'eline'
            && (auth()->user()?->can('api_sources.update') ?? false)) {
            $actions[] = Actions\Action::make('testElineConnection')
                ->label('Test konekcije')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    try {
                        app(ElineSyncOrchestrator::class)->testConnection();

                        Notification::make()
                            ->title('eLine konekcija uspješna')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('eLine konekcija neuspješna')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                });
        } elseif ($this->record?->usesIntegrationApiImport()
            && (auth()->user()?->can('api_sources.update') ?? false)) {
            $actions[] = Actions\Action::make('testIntegrationConnection')
                ->label('Test konekcije')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    try {
                        $this->record->refresh();

                        if (blank($this->record->username) || blank($this->record->password)) {
                            Notification::make()
                                ->title('Kredencijali nisu postavljeni')
                                ->body('Unesite korisničko ime i lozinku za A1 API, sačuvajte zapis, pa ponovo testirajte.')
                                ->warning()
                                ->send();

                            return;
                        }

                        IntegrationApiClient::forSource($this->record)->login();

                        Notification::make()
                            ->title('Konekcija uspješna')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Konekcija neuspješna')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                });
        }

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['interval_preset'] = app(A1SyncSettings::class)->resolvePreset((int) ($data['sync_interval_minutes'] ?? 60));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['target_system_code'] ?? $this->record?->target_system_code) !== 'eline') {
            $preset = (string) ($data['interval_preset'] ?? '60');

            if ($preset !== 'custom') {
                $data['sync_interval_minutes'] = max(1, min(1440, (int) $preset));
            } else {
                $data['sync_interval_minutes'] = max(1, min(1440, (int) ($data['sync_interval_minutes'] ?? 60)));
            }
        }

        unset($data['interval_preset']);

        return $data;
    }
}
