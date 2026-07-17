<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Support\OrderStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderAdminDocumentService
{
    public const MAX_BATCH = 200;

    /**
     * @return Collection<int, int>
     */
    public function parseIds(string $idsParam): Collection
    {
        return collect(explode(',', $idsParam))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return EloquentCollection<int, Order>
     */
    public function loadOrders(Collection $ids, bool $withHistory = false): EloquentCollection
    {
        $with = ['items', 'coupon'];

        if ($withHistory) {
            $with[] = 'statusHistory.changedByUser';
        }

        /** @var EloquentCollection<int, Order> $orders */
        $orders = Order::query()
            ->with($with)
            ->whereIn('id', $ids->all())
            ->orderByDesc('created_at')
            ->get();

        return $orders;
    }

    /**
     * @param  EloquentCollection<int, Order>  $orders
     */
    public function streamExcel(EloquentCollection $orders): StreamedResponse
    {
        $filename = 'narudzbe-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($orders): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($this->excelHeaders()));

            foreach ($orders as $order) {
                $writer->addRow(Row::fromValues($this->excelRow($order)));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  EloquentCollection<int, Order>  $orders
     */
    public function downloadPdf(EloquentCollection $orders, string $view = 'admin.order-pdf'): Response
    {
        $filename = $orders->count() === 1
            ? 'narudzba-'.$orders->first()->order_number.'.pdf'
            : 'narudzbe-'.now()->format('Y-m-d-His').'.pdf';

        return Pdf::loadView($view, [
            'orders' => $orders,
            'single' => $orders->count() === 1,
        ])
            ->setPaper('a4')
            ->download($filename);
    }

    /**
     * @return array<int, string>
     */
    private function excelHeaders(): array
    {
        return [
            'Broj narudžbe',
            'Datum',
            'Status',
            'Ime',
            'Prezime',
            'Telefon',
            'E-mail',
            'Adresa',
            'Grad',
            'Poštanski broj',
            'Plaćanje',
            'Dostava',
            'Međuzbir',
            'Popust',
            'Dostava (KM)',
            'Ukupno',
            'Broj stavki',
            'Kupon',
            'Napomene',
        ];
    }

    /**
     * @return array<int, string|int|float|null>
     */
    private function excelRow(Order $order): array
    {
        return [
            $order->order_number,
            $order->created_at?->format('d.m.Y H:i'),
            OrderStatus::label((string) $order->status),
            $order->first_name,
            $order->last_name,
            $order->phone,
            $order->email,
            $order->address,
            $order->city,
            $order->postal_code,
            $this->paymentMethodLabel($order->payment_method),
            $this->shippingMethodLabel($order->shipping_method),
            (float) $order->subtotal,
            (float) $order->discount_total,
            (float) $order->shipping_fee,
            (float) $order->total,
            (int) $order->items_count,
            $order->coupon?->code,
            $order->notes,
        ];
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'pay_on_delivery', 'cod' => 'Plaćanje pouzećem',
            'bank_transfer' => 'Virman',
            'card' => 'Kartica',
            default => (string) ($method ?? ''),
        };
    }

    private function shippingMethodLabel(?string $method): string
    {
        return match ($method) {
            'delivery' => 'Dostava',
            'pickup' => 'Preuzimanje u radnji',
            default => (string) ($method ?? ''),
        };
    }
}
