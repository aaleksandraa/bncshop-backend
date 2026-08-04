<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSellerOrderStatusRequest;
use App\Models\Order;
use App\Services\Commerce\OrderService;
use App\Support\OrderDisplayLabels;
use App\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SellerOrderController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Single-shop seller panel: any authenticated seller with order permissions
     * can list/view all store orders (not multi-tenant scoped).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->orderByDesc('created_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(min((int) $request->integer('per_page', 20), 50));

        $items = collect($orders->items())
            ->map(fn (Order $order) => $this->formatOrderSummary($order))
            ->all();

        return $this->paginated($orders, $items);
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::query()
            ->with(['items', 'statusHistory.changedByUser'])
            ->findOrFail($id);

        return $this->success($this->formatOrderDetail($order));
    }

    public function updateStatus(UpdateSellerOrderStatusRequest $request, int $id): JsonResponse
    {
        if (! $request->user()?->can('manage_orders')) {
            return $this->error('Nemate dozvolu za promjenu statusa narudžbe.', 403);
        }

        $order = Order::query()->findOrFail($id);
        $validated = $request->validated();

        try {
            $updated = match ($validated['status']) {
                'poslano' => $this->orderService->markAsShippedForSeller(
                    $order,
                    $request->user(),
                    $validated['note'] ?? null,
                ),
                'spremno_za_preuzimanje' => $this->orderService->markReadyForPickupForSeller(
                    $order,
                    $request->user(),
                    $validated['note'] ?? null,
                ),
                'isporučeno' => $this->orderService->markPickedUpForSeller(
                    $order,
                    $request->user(),
                    $validated['note'] ?? null,
                ),
                'otkazano' => $this->orderService->cancelForSeller(
                    $order,
                    $request->user(),
                    $validated['note'] ?? null,
                ),
                default => throw new RuntimeException('Nepoznat status.'),
            };
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        $updated->load(['items', 'statusHistory.changedByUser']);

        return $this->success($this->formatOrderDetail($updated));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => OrderDisplayLabels::statusLabel((string) $order->status, $order),
            'total' => $order->total,
            'items_count' => $order->items_count,
            'customer_name' => trim("{$order->first_name} {$order->last_name}"),
            'phone' => $order->phone,
            'email' => $order->email,
            'created_at' => $order->created_at,
            'shipping_method' => $order->shipping_method,
            'shipping_method_label' => OrderDisplayLabels::shippingMethodLabel((string) $order->shipping_method),
            'is_pickup' => OrderDisplayLabels::isPickup($order),
            'can_mark_shipped' => $this->orderService->canMarkShipped($order),
            'can_mark_ready_for_pickup' => $this->orderService->canMarkReadyForPickup($order),
            'can_mark_picked_up' => $this->orderService->canMarkPickedUp($order),
            'can_cancel' => $this->orderService->canCancel($order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderDetail(Order $order): array
    {
        return [
            ...$this->formatOrderSummary($order),
            'first_name' => $order->first_name,
            'last_name' => $order->last_name,
            'address' => $order->address,
            'city' => $order->city,
            'postal_code' => $order->postal_code,
            'company_name' => $order->company_name,
            'jib' => $order->jib,
            'pdv_number' => $order->pdv_number,
            'notes' => $order->notes,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'shipping_fee' => $order->shipping_fee,
            'payment_method' => $order->payment_method,
            'payment_method_label' => OrderDisplayLabels::paymentMethodLabelForOrder($order),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values()->all(),
            'status_history' => $order->statusHistory
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'old_status' => $entry->old_status,
                    'old_status_label' => OrderDisplayLabels::statusLabel((string) $entry->old_status, $order),
                    'new_status' => $entry->new_status,
                    'new_status_label' => OrderDisplayLabels::statusLabel((string) $entry->new_status, $order),
                    'note' => $entry->note,
                    'changed_by' => $entry->changedByUser?->name,
                    'created_at' => $entry->created_at,
                ])->all(),
        ];
    }
}
