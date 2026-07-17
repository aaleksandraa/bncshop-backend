<?php

namespace App\Filament\Resources\ApiSourceResource\Pages;

use App\Filament\Resources\ApiSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiSource extends CreateRecord
{
    protected static string $resource = ApiSourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['target_system_code'] ?? null) !== 'eline') {
            $preset = (string) ($data['interval_preset'] ?? '60');

            if ($preset !== 'custom') {
                $data['sync_interval_minutes'] = max(1, min(1440, (int) $preset));
            } else {
                $data['sync_interval_minutes'] = max(1, min(1440, (int) ($data['sync_interval_minutes'] ?? 60)));
            }

            $data['auto_sync_enabled'] = (bool) ($data['auto_sync_enabled'] ?? true);
        } else {
            $data['auto_sync_enabled'] = false;
        }

        unset($data['interval_preset']);

        return $data;
    }
}
