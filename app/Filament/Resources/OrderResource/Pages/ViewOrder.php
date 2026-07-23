<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\Commerce\OrderService;
use App\Support\OrderStatus;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.orders.view-order';

    public function resolveRecord(string | int $key): Model
    {
        /** @var Order $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing(['items', 'statusHistory.changedByUser', 'coupon', 'loyaltyReward']);

        return $record;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Narudžba '.$this->record->order_number;
    }

    public function getSubheading(): string | Htmlable | null
    {
        $status = OrderStatus::normalize($this->record->status);
        $label = OrderStatus::label($status);
        $colorClass = match ($status) {
            'isporučeno' => 'order-page-status order-page-status--success',
            'otkazano', 'vraćeno', 'neuspjela_dostava' => 'order-page-status order-page-status--danger',
            'potvrđena', 'spakovano', 'poslano' => 'order-page-status order-page-status--amber',
            default => 'order-page-status order-page-status--neutral',
        };

        return new HtmlString(
            '<span class="'.$colorClass.'">Trenutni status: <strong>'.$label.'</strong></span>'
        );
    }

    protected function refreshOrderRecord(): void
    {
        $this->record->refresh();
        $this->record->loadMissing(['items', 'statusHistory.changedByUser', 'coupon', 'loyaltyReward']);
    }

    protected function getHeaderActions(): array
    {
        $orderService = app(OrderService::class);

        return [
            Actions\ActionGroup::make([
                Actions\Action::make('markConfirmed')
                    ->label('Potvrdi narudžbu')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Order $record): bool => OrderResource::canManageStatus()
                        && OrderResource::canTransitionTo($record, 'potvrđena'))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Napomena (opcionalno)')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if (OrderResource::applyStatusChange($record, ['status' => 'potvrđena', 'note' => $data['note'] ?? null])) {
                            $this->refreshOrderRecord();
                        }
                    }),
                Actions\Action::make('markPacked')
                    ->label('Označi kao spakovano')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn (Order $record): bool => OrderResource::canManageStatus()
                        && OrderResource::canTransitionTo($record, 'spakovano'))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Napomena (opcionalno)')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if (OrderResource::applyStatusChange($record, ['status' => 'spakovano', 'note' => $data['note'] ?? null])) {
                            $this->refreshOrderRecord();
                        }
                    }),
                Actions\Action::make('markShipped')
                    ->label('Označi kao poslano')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record): bool => OrderResource::canManageStatus()
                        && $orderService->canMarkShipped($record))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Napomena (opcionalno)')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if (OrderResource::applyMarkShipped($record, $data['note'] ?? null)) {
                            $this->refreshOrderRecord();
                        }
                    }),
                Actions\Action::make('markDelivered')
                    ->label('Označi kao isporučeno')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record): bool => OrderResource::canManageStatus()
                        && OrderResource::canTransitionTo($record, 'isporučeno'))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Napomena (opcionalno)')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if (OrderResource::applyStatusChange($record, ['status' => 'isporučeno', 'note' => $data['note'] ?? null])) {
                            $this->refreshOrderRecord();
                        }
                    }),
                Actions\Action::make('cancelOrder')
                    ->label('Otkaži narudžbu')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Otkaži narudžbu')
                    ->modalDescription('Kupcu će biti poslan e-mail o otkazivanju narudžbe.')
                    ->visible(fn (Order $record): bool => OrderResource::canManageStatus()
                        && $orderService->canCancel($record))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Razlog otkazivanja (opcionalno)')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        if (OrderResource::applyCancel($record, $data['note'] ?? null)) {
                            $this->refreshOrderRecord();
                        }
                    }),
            ])
                ->label('Status narudžbe')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->button()
                ->color('primary')
                ->visible(function (Order $record) use ($orderService): bool {
                    if (! OrderResource::canManageStatus()) {
                        return false;
                    }

                    return $orderService->canMarkShipped($record)
                        || OrderResource::canTransitionTo($record, 'potvrđena')
                        || OrderResource::canTransitionTo($record, 'spakovano')
                        || OrderResource::canTransitionTo($record, 'isporučeno')
                        || $orderService->canCancel($record);
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('print')
                    ->label('Štampaj narudžbu')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record): string => route('filament.admin.orders.print', $record))
                    ->openUrlInNewTab(),
                Actions\Action::make('printPacking')
                    ->label('List za pakovanje')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (Order $record): string => route('filament.admin.orders.packing-print', $record))
                    ->openUrlInNewTab(),
                Actions\Action::make('exportPdf')
                    ->label('Preuzmi PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Order $record): string => route('filament.admin.orders.export-pdf', $record)),
                Actions\Action::make('exportPackingPdf')
                    ->label('PDF pakovanje')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->url(fn (Order $record): string => route('filament.admin.orders.export-packing-pdf', $record)),
                Actions\Action::make('exportExcel')
                    ->label('Preuzmi Excel')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (Order $record): string => route('filament.admin.orders.export-excel', $record)),
            ])
                ->label('Export / štampa')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->visible(fn (): bool => OrderResource::canPrint()),
            Actions\Action::make('changeStatus')
                ->label('Promijeni status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => OrderResource::canManageStatus())
                ->form(fn (Order $record): array => OrderResource::changeStatusForm($record))
                ->action(function (Order $record, array $data): void {
                    if (OrderResource::applyStatusChange($record, $data)) {
                        $this->refreshOrderRecord();
                    }
                }),
            Actions\DeleteAction::make()
                ->modalHeading('Obriši narudžbu')
                ->modalDescription('Narudžba i sve povezane stavke bit će trajno obrisane. Koristite za test narudžbe — ova radnja se ne može poništiti.')
                ->successRedirectUrl(OrderResource::getUrl('index')),
        ];
    }
}
