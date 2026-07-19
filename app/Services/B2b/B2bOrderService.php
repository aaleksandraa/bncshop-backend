<?php

namespace App\Services\B2b;

use App\Models\B2bOrder;
use App\Models\B2bOrderStatusHistory;
use App\Models\B2bProduct;
use App\Models\User;
use App\Support\B2bOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class B2bOrderService
{
    public function __construct(
        private readonly B2bAccessMailer $mailer,
    ) {}

    public function updateStatus(B2bOrder $order, string $newStatus, ?User $changedBy = null, ?string $note = null): B2bOrder
    {
        if (! in_array($newStatus, B2bOrderStatus::all(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Nepoznat status narudžbe.'],
            ]);
        }

        if ($order->status === $newStatus) {
            return $order;
        }

        $oldStatus = $order->status;

        $order = DB::transaction(function () use ($order, $newStatus, $changedBy, $note, $oldStatus): B2bOrder {

            if ($newStatus === B2bOrderStatus::OTKAZANA && $oldStatus !== B2bOrderStatus::OTKAZANA) {
                $this->restoreStock($order);
            }

            $order->update(['status' => $newStatus]);

            B2bOrderStatusHistory::query()->create([
                'b2b_order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'changed_by' => $changedBy?->id,
                'note' => $note,
            ]);

            $order->load('customer.user');

            return $order->fresh(['items', 'customer.user']);
        });

        if ($order->customer?->user) {
            $this->mailer->sendOrderStatusChanged($order, $oldStatus);
        }

        return $order;
    }

    private function restoreStock(B2bOrder $order): void
    {
        foreach ($order->items as $item) {
            if ($item->b2b_product_id) {
                B2bProduct::query()
                    ->whereKey($item->b2b_product_id)
                    ->increment('stock_quantity', $item->quantity);
            }
        }
    }
}
