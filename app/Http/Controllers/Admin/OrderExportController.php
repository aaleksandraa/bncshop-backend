<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesOrderAdminAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderAdminDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportController extends Controller
{
    use AuthorizesOrderAdminAccess;

    public function __construct(
        private readonly OrderAdminDocumentService $documents,
    ) {}

    public function excel(Order $order): StreamedResponse
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->documents->loadOrders(collect([$order->id]));

        abort_if($orders->isEmpty(), 404);

        return $this->documents->streamExcel($orders);
    }

    public function excelBatch(Request $request): StreamedResponse
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->resolveBatchOrders($request);

        return $this->documents->streamExcel($orders);
    }

    public function pdf(Order $order): Response
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->documents->loadOrders(collect([$order->id]), withHistory: true);

        abort_if($orders->isEmpty(), 404);

        return $this->documents->downloadPdf($orders);
    }

    public function pdfBatch(Request $request): Response
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->resolveBatchOrders($request, withHistory: true);

        return $this->documents->downloadPdf($orders);
    }

    public function packingPdf(Order $order): Response
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->documents->loadOrders(collect([$order->id]));

        abort_if($orders->isEmpty(), 404);

        return $this->documents->downloadPdf($orders, 'admin.order-packing-pdf');
    }

    public function packingPdfBatch(Request $request): Response
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->resolveBatchOrders($request);

        return $this->documents->downloadPdf($orders, 'admin.order-packing-pdf');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    private function resolveBatchOrders(Request $request, bool $withHistory = false): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->documents->parseIds((string) $request->query('ids', ''));

        abort_if($ids->isEmpty(), 404);
        abort_if(
            $ids->count() > OrderAdminDocumentService::MAX_BATCH,
            422,
            'Maksimalno '.OrderAdminDocumentService::MAX_BATCH.' narudžbi po exportu.',
        );

        $orders = $this->documents->loadOrders($ids, $withHistory);

        abort_if($orders->isEmpty(), 404);

        return $orders;
    }
}
