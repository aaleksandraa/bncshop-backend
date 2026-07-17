<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Requests\Api\V1\TrackOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends Controller
{
    use RespondsWithJson;

    public function track(string $token): JsonResponse
    {
        $order = $this->findOrderByToken($token);

        return $this->success($this->summaryPayload($order));
    }

    public function trackWithVerification(TrackOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $order = $this->findOrderByToken($validated['token']);

        if (! $this->emailMatchesOrder($order, $validated['email'])) {
            throw new NotFoundHttpException('Narudžba nije pronađena.');
        }

        return $this->success($this->detailedPayload($order));
    }

    private function findOrderByToken(string $token): Order
    {
        return Order::query()
            ->where('tracking_token', $token)
            ->with(['items', 'statusHistory'])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'requires_verification' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailedPayload(Order $order): array
    {
        return array_merge($this->summaryPayload($order), [
            'email' => $order->email,
            'total' => $order->total,
            'shipping_fee' => $order->shipping_fee,
            'shipping_method' => $order->shipping_method,
            'items_count' => $order->items_count,
            'items' => $order->items->map(fn (OrderItem $item) => $this->formatTrackedItem($item))->all(),
            'status_history' => $order->statusHistory
                ->map(fn (OrderStatusHistory $entry) => $this->formatStatusHistory($entry))
                ->all(),
            'requires_verification' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTrackedItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => $item->line_total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStatusHistory(OrderStatusHistory $entry): array
    {
        return [
            'id' => $entry->id,
            'old_status' => $entry->old_status,
            'new_status' => $entry->new_status,
            'note' => $entry->note,
            'created_at' => $entry->created_at,
        ];
    }

    private function emailMatchesOrder(Order $order, string $email): bool
    {
        if (! $order->email) {
            return false;
        }

        return strtolower(trim($order->email)) === strtolower(trim($email));
    }
}
