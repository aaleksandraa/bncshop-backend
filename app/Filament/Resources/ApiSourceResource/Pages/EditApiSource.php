<?php

namespace App\Filament\Resources\ApiSourceResource\Pages;

use App\Filament\Resources\ApiSourceResource;
use App\Services\Sync\A1SyncSettings;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiSource extends EditRecord
{
    protected static string $resource = ApiSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
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
