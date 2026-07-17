<?php

namespace App\Filament\B2b\Resources\B2bOrderResource\Pages;

use App\Filament\B2b\Resources\B2bOrderResource;
use App\Services\B2b\B2bOrderService;
use Filament\Resources\Pages\EditRecord;

class EditB2bOrder extends EditRecord
{
    protected static string $resource = B2bOrderResource::class;

    protected ?string $previousStatus = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function beforeSave(): void
    {
        $this->previousStatus = $this->record->status;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['status']);

        return $data;
    }

    protected function afterSave(): void
    {
        $newStatus = $this->form->getState()['status'] ?? null;

        if ($newStatus && $newStatus !== $this->previousStatus) {
            app(B2bOrderService::class)->updateStatus(
                $this->record->fresh(),
                $newStatus,
                auth()->user(),
            );
        }
    }
}
