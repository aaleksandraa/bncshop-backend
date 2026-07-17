<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\Admin\OrderAdminDocumentService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Js;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('printFiltered')
                    ->label('Štampaj filtrirane')
                    ->icon('heroicon-o-printer')
                    ->action(fn (): mixed => $this->openBatchUrl('filament.admin.orders.print-batch')),
                Actions\Action::make('printPackingFiltered')
                    ->label('Pakovanje (filtrirane)')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->action(fn (): mixed => $this->openBatchUrl('filament.admin.orders.packing-print-batch')),
                Actions\Action::make('exportExcelFiltered')
                    ->label('Excel (filtrirane)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (): mixed => $this->downloadBatchUrl('filament.admin.orders.export-excel-batch')),
                Actions\Action::make('exportPdfFiltered')
                    ->label('PDF (filtrirane)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (): mixed => $this->downloadBatchUrl('filament.admin.orders.export-pdf-batch')),
                Actions\Action::make('exportPackingPdfFiltered')
                    ->label('PDF pakovanje (filtrirane)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->action(fn (): mixed => $this->downloadBatchUrl('filament.admin.orders.export-packing-pdf-batch')),
            ])
                ->label('Export / štampa')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->visible(fn (): bool => OrderResource::canPrint()),
        ];
    }

    private function filteredOrderIds(): ?string
    {
        $ids = $this->getFilteredTableQuery()
            ->orderByDesc('created_at')
            ->limit(OrderAdminDocumentService::MAX_BATCH)
            ->pluck('id');

        if ($ids->isEmpty()) {
            Notification::make()
                ->title('Nema narudžbi za odabrani export.')
                ->warning()
                ->send();

            return null;
        }

        return $ids->implode(',');
    }

    private function openBatchUrl(string $routeName): void
    {
        $ids = $this->filteredOrderIds();

        if ($ids === null) {
            return;
        }

        $url = route($routeName, ['ids' => $ids]);
        $this->js('window.open('.Js::from($url).', "_blank")');
    }

    private function downloadBatchUrl(string $routeName): void
    {
        $ids = $this->filteredOrderIds();

        if ($ids === null) {
            return;
        }

        $url = route($routeName, ['ids' => $ids]);
        $this->js('window.location.href = '.Js::from($url));
    }
}
