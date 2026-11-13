<?php

namespace App\Filament\Resources\AnanasOrderResource\Pages;

use App\Filament\Resources\AnanasOrderResource;
use App\Models\AnanasOrder;
use App\Services\Ananas\AnanasOrderConfirmService;
use App\Services\Ananas\AnanasOrderPullService;
use App\Services\Ananas\AnanasSyncSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAnanasOrder extends ViewRecord
{
    protected static string $resource = AnanasOrderResource::class;

    public function resolveRecord(string | int $key): Model
    {
        /** @var AnanasOrder $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing(['items.product', 'items.mapping']);

        return $record;
    }

    public function getTitle(): string
    {
        return 'Ananas '.$this->record->ananas_order_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pull_one')
                ->label('Osvježi')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => AnanasOrderResource::canPullOrConfirm())
                ->action(function (AnanasOrderPullService $pullService, AnanasSyncSettings $settings): void {
                    if (! $settings->isOrdersPullEnabled()) {
                        Notification::make()->title('Povlačenje narudžbi je isključeno.')->warning()->send();

                        return;
                    }

                    $pullService->pullSingleById($this->record->ananas_order_id);
                    $this->refreshFormData(['record']);
                    Notification::make()->title('Narudžba osvježena.')->success()->send();
                }),
            Actions\Action::make('confirm')
                ->label('Potvrdi količine')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => AnanasOrderResource::canPullOrConfirm() && $this->record->canConfirm())
                ->form(function (): array {
                    $fields = [];

                    foreach ($this->record->items as $item) {
                        if ($item->confirmableInventoryId() <= 0) {
                            continue;
                        }

                        $fields[] = Forms\Components\TextInput::make('qty_'.$item->id)
                            ->label($item->product_name ?: $item->product_ean)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue((int) $item->quantity_ordered)
                            ->default((int) $item->quantity_ordered)
                            ->required();
                    }

                    if ($fields === []) {
                        $fields[] = Forms\Components\Placeholder::make('no_items')
                            ->label('')
                            ->content('Nema stavki sa Ananas mapiranjem za potvrdu.');
                    }

                    return $fields;
                })
                ->action(function (array $data, AnanasOrderConfirmService $confirmService): void {
                    $quantities = [];

                    foreach ($this->record->items as $item) {
                        $key = 'qty_'.$item->id;

                        if (array_key_exists($key, $data)) {
                            $quantities[$item->id] = (int) $data[$key];
                        }
                    }

                    try {
                        $confirmService->confirm($this->record, $quantities);
                        $this->record->refresh();
                        Notification::make()->title('Narudžba potvrđena na Ananasu.')->success()->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Potvrda nije uspjela')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
