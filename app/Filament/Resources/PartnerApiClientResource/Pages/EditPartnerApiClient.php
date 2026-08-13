<?php

namespace App\Filament\Resources\PartnerApiClientResource\Pages;

use App\Filament\Resources\PartnerApiClientResource;
use App\Models\PartnerApiClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPartnerApiClient extends EditRecord
{
    protected static string $resource = PartnerApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rotateApiKey')
                ->label('Generiši novi API ključ')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var PartnerApiClient $client */
                    $client = $this->record;
                    $plainKey = $client->rotateApiKey();

                    $this->refreshFormData(['api_key_hint']);

                    Notification::make()
                        ->title('Novi API ključ generisan')
                        ->body($plainKey)
                        ->persistent()
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
}
