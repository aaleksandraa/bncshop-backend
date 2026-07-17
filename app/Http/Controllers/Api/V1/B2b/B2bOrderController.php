<?php

namespace App\Http\Controllers\Api\V1\B2b;

use App\Http\Controllers\Api\V1\B2b\Concerns\FormatsB2bResponses;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\B2bOrder;
use App\Services\B2b\B2bOrderInvoicePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class B2bOrderController extends Controller
{
    use FormatsB2bResponses;
    use RespondsWithJson;

    public function __construct(
        private readonly B2bOrderInvoicePdf $invoicePdf,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->b2bCustomer($request);

        $orders = B2bOrder::query()
            ->withSum('items as items_count', 'quantity')
            ->where('b2b_customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated(
            $orders,
            collect($orders->items())
                ->map(fn (B2bOrder $order) => $this->formatOrderSummary($order))
                ->values()
                ->all()
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->b2bCustomer($request);

        $order = B2bOrder::query()
            ->with('items')
            ->where('b2b_customer_id', $customer->id)
            ->whereKey($id)
            ->firstOrFail();

        return $this->success($this->formatOrder($order));
    }

    public function invoice(Request $request, int $id): Response
    {
        $customer = $this->b2bCustomer($request);

        $order = B2bOrder::query()
            ->with('items')
            ->where('b2b_customer_id', $customer->id)
            ->whereKey($id)
            ->firstOrFail();

        return $this->invoicePdf->download($order);
    }
}
