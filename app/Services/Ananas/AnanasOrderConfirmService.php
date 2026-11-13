<?php

namespace App\Services\Ananas;

use App\Models\AnanasOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnanasOrderConfirmService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasSyncSettings $settings,
        private readonly AnanasOrderPullService $pullService,
        private readonly AnanasCatalogWriteGuard $writeGuard,
    ) {}

    /**
     * @param  array<int, int>  $quantitiesByItemId  local ananas_order_items.id => quantity
     */
    public function confirm(AnanasOrder $order, array $quantitiesByItemId): void
    {
        if (! $this->settings->isOrdersPullEnabled()) {
            throw new RuntimeException('Povlačenje narudžbi je isključeno u Ananas postavkama.');
        }

        $allowProduction = $this->settings->isProduction();
        $this->writeGuard->assertAllowed($allowProduction);

        $warehouseId = $this->apiClient->defaultWarehouseAddressId();

        if ($warehouseId === null) {
            throw new RuntimeException('Ananas nema konfigurisan magacin (merchant-warehouses).');
        }

        $order->loadMissing('items');
        $payload = [];

        foreach ($order->items as $item) {
            $qty = $quantitiesByItemId[$item->id] ?? (int) $item->quantity_ordered;
            $qty = max(0, min($qty, (int) $item->quantity_ordered));
            $inventoryId = $item->confirmableInventoryId();

            if ($inventoryId <= 0) {
                throw new RuntimeException(sprintf(
                    'Stavka "%s" nema Ananas merchant inventory id (mapiranje nedostaje).',
                    $item->product_name ?: $item->product_ean,
                ));
            }

            if ($qty <= 0) {
                continue;
            }

            $payload[] = [
                'merchantInventoryId' => $inventoryId,
                'confirmedQuantities' => [
                    [
                        'warehouseAddressId' => $warehouseId,
                        'quantity' => $qty,
                    ],
                ],
            ];
        }

        if ($payload === []) {
            throw new RuntimeException('Nema stavki za potvrdu.');
        }

        DB::transaction(function () use ($order, $payload, $allowProduction, $quantitiesByItemId): void {
            $this->apiClient->confirmOrderQuantities($order->ananas_order_id, $payload, $allowProduction);

            foreach ($order->items as $item) {
                if (! array_key_exists($item->id, $quantitiesByItemId)) {
                    continue;
                }

                $item->quantity_confirmed = max(0, (int) $quantitiesByItemId[$item->id]);
                $item->save();
            }

            $order->confirmed_at = now();
            $order->save();
        });

        $this->pullService->pullSingleById($order->ananas_order_id);
    }
}
