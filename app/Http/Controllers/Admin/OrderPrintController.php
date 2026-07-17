<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesOrderAdminAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderAdminDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    use AuthorizesOrderAdminAccess;

    public function __construct(
        private readonly OrderAdminDocumentService $documents,
    ) {}

    public function show(Order $order): View
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->documents->loadOrders(collect([$order->id]), withHistory: true);

        abort_if($orders->isEmpty(), 404);

        return view('admin.order-print', [
            'orders' => $orders,
            'single' => true,
        ]);
    }

    public function batch(Request $request): View
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->resolveBatchOrders($request, withHistory: true);

        return view('admin.order-print', [
            'orders' => $orders,
            'single' => $orders->count() === 1,
        ]);
    }

    public function packing(Order $order): View
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->documents->loadOrders(collect([$order->id]));

        abort_if($orders->isEmpty(), 404);

        return view('admin.order-packing-print', [
            'orders' => $orders,
            'single' => true,
        ]);
    }

    public function packingBatch(Request $request): View
    {
        $this->authorizeOrderAdminAccess();

        $orders = $this->resolveBatchOrders($request);

        return view('admin.order-packing-print', [
            'orders' => $orders,
            'single' => $orders->count() === 1,
        ]);
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
            'Maksimalno '.OrderAdminDocumentService::MAX_BATCH.' narudžbi po štampi.',
        );

        $orders = $this->documents->loadOrders($ids, $withHistory);

        abort_if($orders->isEmpty(), 404);

        return $orders;
    }
}
