<?php

namespace App\Services\Commerce;

use App\Mail\OrderStatusChanged;
use App\Mail\TemplatedOrderMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Support\OrderDisplayLabels;
use App\Support\OrderNotificationMail;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OrderService
{
    /** @var array<string, array<int, string>> */
    private const DELIVERY_TRANSITIONS = [
        'nova' => ['u_obradi', 'potvrđena', 'otkazano'],
        'u_obradi' => ['potvrđena', 'otkazano'],
        'potvrđena' => ['spakovano', 'otkazano'],
        'spakovano' => ['poslano', 'otkazano'],
        'poslano' => ['isporučeno', 'neuspjela_dostava', 'otkazano'],
        'isporučeno' => ['vraćeno', 'arhivirano'],
        'neuspjela_dostava' => ['u_obradi', 'otkazano', 'arhivirano'],
        'vraćeno' => ['arhivirano'],
        'otkazano' => ['arhivirano'],
        'arhivirano' => [],
    ];

    /** @var array<string, array<int, string>> */
    private const PICKUP_TRANSITIONS = [
        'nova' => ['u_obradi', 'potvrđena', 'otkazano'],
        'u_obradi' => ['potvrđena', 'otkazano'],
        'potvrđena' => ['spremno_za_preuzimanje', 'otkazano'],
        'spremno_za_preuzimanje' => ['isporučeno', 'otkazano'],
        'isporučeno' => ['vraćeno', 'arhivirano'],
        'vraćeno' => ['arhivirano'],
        'otkazano' => ['arhivirano'],
        'arhivirano' => [],
    ];

    /** @var array<string, array<int, string>> */
    private const SHIPPING_PATHS = [
        'nova' => ['potvrđena', 'spakovano', 'poslano'],
        'u_obradi' => ['potvrđena', 'spakovano', 'poslano'],
        'potvrđena' => ['spakovano', 'poslano'],
        'spakovano' => ['poslano'],
        'neuspjela_dostava' => ['u_obradi', 'potvrđena', 'spakovano', 'poslano'],
    ];

    /** @var array<string, array<int, string>> */
    private const PICKUP_READY_PATHS = [
        'nova' => ['potvrđena', 'spremno_za_preuzimanje'],
        'u_obradi' => ['potvrđena', 'spremno_za_preuzimanje'],
        'potvrđena' => ['spremno_za_preuzimanje'],
    ];

    public function __construct(
        private readonly StockService $stockService,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function allowedTransitions(?string $status, ?Order $order = null): array
    {
        $transitions = $this->transitionsFor($order);

        return $transitions[$this->normalizeStatus($status)] ?? [];
    }

    public function canMarkShipped(Order $order): bool
    {
        if (OrderDisplayLabels::isPickup($order)) {
            return false;
        }

        $status = $this->normalizeStatus($order->status);

        if (in_array($status, ['poslano', 'isporučeno', 'otkazano', 'arhivirano', 'vraćeno'], true)) {
            return false;
        }

        return isset(self::SHIPPING_PATHS[$status]);
    }

    public function canMarkReadyForPickup(Order $order): bool
    {
        if (! OrderDisplayLabels::isPickup($order)) {
            return false;
        }

        $status = $this->normalizeStatus($order->status);

        if (in_array($status, ['spremno_za_preuzimanje', 'isporučeno', 'otkazano', 'arhivirano', 'vraćeno'], true)) {
            return false;
        }

        return isset(self::PICKUP_READY_PATHS[$status]);
    }

    public function canMarkPickedUp(Order $order): bool
    {
        if (! OrderDisplayLabels::isPickup($order)) {
            return false;
        }

        return $this->normalizeStatus($order->status) === 'spremno_za_preuzimanje';
    }

    public function canCancel(Order $order): bool
    {
        return in_array('otkazano', $this->allowedTransitions($order->status, $order), true);
    }

    public function transition(Order $order, string $newStatus, ?User $changedBy = null, ?string $note = null, bool $restoreStockOnReturn = false): Order
    {
        $allowed = $this->allowedTransitions($order->status, $order);

        if (! in_array($newStatus, $allowed, true)) {
            throw new RuntimeException("Status transition from {$order->status} to {$newStatus} is not allowed.");
        }

        return DB::transaction(function () use ($order, $newStatus, $changedBy, $note, $restoreStockOnReturn): Order {
            $oldStatus = $this->normalizeStatus($order->status);

            $this->applyStockEffects($order, $oldStatus, $newStatus, $restoreStockOnReturn);

            $order->update(['status' => $newStatus]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy?->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $order = $order->fresh(['items.product']);

            $this->sendStatusEmail($order, $oldStatus, $newStatus);
            $this->handleLoyaltyEffects($order, $oldStatus, $newStatus);

            return $order;
        });
    }

    public function markAsShippedForSeller(Order $order, ?User $changedBy = null, ?string $note = null): Order
    {
        if ($this->normalizeStatus($order->status) === 'poslano') {
            return $order->fresh(['items', 'statusHistory']);
        }

        if (! $this->canMarkShipped($order)) {
            throw new RuntimeException('Narudžba se ne može označiti kao poslana u trenutnom statusu.');
        }

        $path = self::SHIPPING_PATHS[$this->normalizeStatus($order->status)] ?? [];

        return DB::transaction(function () use ($order, $path, $changedBy, $note): Order {
            $current = $order->fresh(['items.product']);

            foreach ($path as $nextStatus) {
                $current = $this->transition($current, $nextStatus, $changedBy, $note);
            }

            return $current->fresh(['items', 'statusHistory']);
        });
    }

    public function markReadyForPickupForSeller(Order $order, ?User $changedBy = null, ?string $note = null): Order
    {
        if ($this->normalizeStatus($order->status) === 'spremno_za_preuzimanje') {
            return $order->fresh(['items', 'statusHistory']);
        }

        if (! $this->canMarkReadyForPickup($order)) {
            throw new RuntimeException('Narudžba se ne može označiti kao spremna za preuzimanje u trenutnom statusu.');
        }

        $path = self::PICKUP_READY_PATHS[$this->normalizeStatus($order->status)] ?? [];

        return DB::transaction(function () use ($order, $path, $changedBy, $note): Order {
            $current = $order->fresh(['items.product']);

            foreach ($path as $nextStatus) {
                $current = $this->transition($current, $nextStatus, $changedBy, $note);
            }

            return $current->fresh(['items', 'statusHistory']);
        });
    }

    public function markPickedUpForSeller(Order $order, ?User $changedBy = null, ?string $note = null): Order
    {
        if ($this->normalizeStatus($order->status) === 'isporučeno') {
            return $order->fresh(['items', 'statusHistory']);
        }

        if (! $this->canMarkPickedUp($order)) {
            throw new RuntimeException('Narudžba se ne može označiti kao preuzeta u trenutnom statusu.');
        }

        return $this->transition($order, 'isporučeno', $changedBy, $note);
    }

    public function cancelForSeller(Order $order, ?User $changedBy = null, ?string $note = null): Order
    {
        if (! $this->canCancel($order)) {
            throw new RuntimeException('Narudžba se ne može otkazati u trenutnom statusu.');
        }

        return $this->transition($order, 'otkazano', $changedBy, $note);
    }

    private function handleLoyaltyEffects(Order $order, string $oldStatus, string $newStatus): void
    {
        if ($newStatus === 'isporučeno') {
            $this->loyaltyService->awardForOrder($order->fresh());

            return;
        }

        if (in_array($newStatus, ['otkazano', 'vraćeno'], true) && $oldStatus === 'isporučeno') {
            $this->loyaltyService->clawbackForOrder($order->fresh());
        }
    }

    private function sendStatusEmail(Order $order, string $oldStatus, string $newStatus): void
    {
        $customerStatuses = ['poslano', 'otkazano', 'isporučeno', 'spremno_za_preuzimanje'];

        if (in_array($newStatus, $customerStatuses, true) && $order->email) {
            Mail::to($order->email)->queue(new OrderStatusChanged($order, $oldStatus, $newStatus));
        }

        $statusVariables = [
            'old_status' => OrderDisplayLabels::statusLabel($oldStatus, $order),
            'new_status' => OrderDisplayLabels::statusLabel($newStatus, $order),
        ];

        $sellerTemplate = match ($newStatus) {
            'poslano' => 'order_shipped_seller',
            'spremno_za_preuzimanje' => 'order_ready_for_pickup_seller',
            'otkazano' => 'order_cancelled_seller',
            default => 'order_status_changed_seller',
        };

        foreach (OrderNotificationMail::recipients() as $recipient) {
            Mail::to($recipient)->queue(new TemplatedOrderMail(
                templateSlug: $sellerTemplate,
                order: $order,
                extraVariables: $statusVariables,
            ));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function transitionsFor(?Order $order): array
    {
        if ($order !== null && OrderDisplayLabels::isPickup($order)) {
            return self::PICKUP_TRANSITIONS;
        }

        return self::DELIVERY_TRANSITIONS;
    }

    private function normalizeStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'nova';
        }

        return $status;
    }

    private function applyStockEffects(Order $order, string $from, string $to, bool $restoreStockOnReturn): void
    {
        $order->loadMissing('items.product');
        $isPickup = OrderDisplayLabels::isPickup($order);
        $shipStatus = $isPickup ? 'spremno_za_preuzimanje' : 'poslano';

        if ($to === 'otkazano' && ! in_array($from, [$shipStatus, 'isporučeno', 'vraćeno'], true)) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $this->stockService->release($item->product, (int) $item->quantity);
                }
            }
        }

        if ($to === $shipStatus && $from !== $shipStatus) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $this->stockService->deduct($item->product, (int) $item->quantity);
                }
            }
        }

        if ($to === 'vraćeno' && $restoreStockOnReturn) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $product = $item->product;
                    if ($product->manual_stock_override !== null) {
                        $product->manual_stock_override += (int) $item->quantity;
                    } else {
                        $product->api_stock += (int) $item->quantity;
                    }
                    $product->available_stock += (int) $item->quantity;
                    $product->syncStockStatus();
                    $product->save();
                }
            }
        }
    }
}
