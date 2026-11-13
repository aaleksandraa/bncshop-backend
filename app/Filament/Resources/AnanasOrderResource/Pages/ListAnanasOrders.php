<?php

namespace App\Filament\Resources\AnanasOrderResource\Pages;

use App\Filament\Resources\AnanasOrderResource;
use App\Services\Ananas\AnanasOrderPullService;
use App\Services\Ananas\AnanasSyncSettings;
use App\Support\Ananas\AnanasOrderStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAnanasOrders extends ListRecords
{
    protected static string $resource = AnanasOrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Sve'),
            'awaiting' => Tab::make('Čeka potvrdu')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_bucket', AnanasOrderStatus::BUCKET_AWAITING)),
            'processing' => Tab::make('U obradi')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_bucket', AnanasOrderStatus::BUCKET_PROCESSING)),
            'on_delivery' => Tab::make('Na dostavi')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_bucket', AnanasOrderStatus::BUCKET_ON_DELIVERY)),
            'completed' => Tab::make('Završeno')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_bucket', AnanasOrderStatus::BUCKET_COMPLETED)),
            'cancelled' => Tab::make('Otkazano / nije isporučeno')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status_bucket', AnanasOrderStatus::BUCKET_CANCELLED)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pull')
                ->label('Povuci')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => AnanasOrderResource::canPullOrConfirm())
                ->action(function (AnanasOrderPullService $pullService, AnanasSyncSettings $settings): void {
                    if (! AnanasOrderResource::canPullOrConfirm()) {
                        return;
                    }

                    if (! $settings->isOrdersPullEnabled()) {
                        Notification::make()
                            ->title('Povlačenje narudžbi je isključeno u Ananas postavkama.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $result = $pullService->pull();

                    Notification::make()
                        ->title('Povlačenje završeno')
                        ->body(sprintf(
                            'Novo: %d · Ažurirano: %d · Mail: %d',
                            $result['created'],
                            $result['updated'],
                            $result['notified'],
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }
}
