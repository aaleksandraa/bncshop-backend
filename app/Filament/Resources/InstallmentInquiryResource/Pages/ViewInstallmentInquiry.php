<?php

namespace App\Filament\Resources\InstallmentInquiryResource\Pages;

use App\Filament\Resources\InstallmentInquiryResource;
use App\Models\InstallmentInquiry;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInstallmentInquiry extends ViewRecord
{
    protected static string $resource = InstallmentInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('changeStatus')
                ->label('Promijeni status')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => InstallmentInquiryResource::canEdit($this->getRecord()))
                ->fillForm(fn (): array => [
                    'status' => $this->getRecord()->status,
                    'cancellation_reason' => $this->getRecord()->cancellation_reason,
                ])
                ->form(InstallmentInquiryResource::statusForm())
                ->action(function (array $data): void {
                    $record = $this->getRecord();

                    if (! $record instanceof InstallmentInquiry) {
                        return;
                    }

                    $actor = auth()->user();
                    $record->applyStatus(
                        $data['status'],
                        $data['cancellation_reason'] ?? null,
                        $actor instanceof User ? $actor : null,
                    );

                    Notification::make()
                        ->title($data['status'] === InstallmentInquiry::STATUS_CANCELLED ? 'Upit je otkazan' : 'Status ažuriran')
                        ->success()
                        ->send();
                }),
        ];
    }
}
