<?php

namespace App\Filament\Resources\PartnerApiClientResource\Pages;

use App\Filament\Resources\PartnerApiClientResource;
use App\Models\PartnerApiClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerApiClient extends CreateRecord
{
    protected static string $resource = PartnerApiClientResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $invalidIps = PartnerApiClient::invalidAllowedIps((string) ($data['allowed_ips_text'] ?? ''));

        if ($invalidIps !== []) {
            Notification::make()
                ->title('Neispravne IP adrese')
                ->body('Provjerite unos: '.implode(', ', $invalidIps))
                ->danger()
                ->send();

            $this->halt();
        }

        return PartnerApiClientResource::mutateFormDataBeforeSave($data);
    }

    protected function afterCreate(): void
    {
        /** @var PartnerApiClient $client */
        $client = $this->record;
        $plainKey = $client->rotateApiKey();

        Notification::make()
            ->title('Partner kreiran — novi API ključ')
            ->body($plainKey)
            ->persistent()
            ->success()
            ->send();
    }
}
