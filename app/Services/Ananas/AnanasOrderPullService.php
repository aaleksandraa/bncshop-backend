<?php

namespace App\Services\Ananas;

use App\Mail\AnanasOrderNotificationAdmin;
use App\Models\AnanasOrder;
use App\Models\AnanasOrderItem;
use App\Models\AnanasProductMapping;
use App\Support\Ananas\AnanasOrderStatus;
use App\Support\OrderNotificationMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnanasOrderPullService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasSyncSettings $settings,
        private readonly AnanasCommittedStockService $committedStock,
    ) {}

    /**
     * @return array{created: int, updated: int, notified: int, errors: list<string>}
     */
    public function pull(bool $backfill = false, ?Carbon $since = null): array
    {
        if (! $this->settings->isOrdersPullEnabled()) {
            return ['created' => 0, 'updated' => 0, 'notified' => 0, 'errors' => ['Orders pull is disabled.']];
        }

        if (! $this->settings->isEnabled() || ! $this->settings->hasCredentials()) {
            return ['created' => 0, 'updated' => 0, 'notified' => 0, 'errors' => ['Ananas export or credentials unavailable.']];
        }

        $errors = [];
        $created = 0;
        $updated = 0;
        $notified = 0;

        $orderRows = [];

        try {
            $orderRows = array_merge(
                $orderRows,
                $this->fetchAllOrderPages(['statusGroup' => 'SG_FOR_CONFIRMATION', 'size' => 100]),
            );

            if ($backfill && $since !== null) {
                $orderRows = array_merge(
                    $orderRows,
                    $this->fetchAllOrderPages([
                        'dateFrom' => $since->clone()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                        'size' => 100,
                    ]),
                );
            } else {
                $watermark = $this->settings->ordersPullWatermark();

                if ($watermark !== null) {
                    $from = $watermark->clone()->subMinutes(30);
                    $orderRows = array_merge(
                        $orderRows,
                        $this->fetchAllOrderPages([
                            'dateFrom' => $from->utc()->format('Y-m-d\TH:i:s.v\Z'),
                            'size' => 100,
                        ]),
                    );
                }
            }

            foreach ($this->uniqueOrdersById($orderRows) as $payload) {
                $wasNew = AnanasOrder::query()
                    ->where('ananas_order_id', (string) ($payload['id'] ?? ''))
                    ->doesntExist();

                $order = $this->upsertOrderFromPayload($payload);

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }

                if (! $backfill && $wasNew && $this->shouldNotify($order)) {
                    $this->sendNotification($order);
                    $order->notified_at = now();
                    $order->save();
                    $notified++;
                }
            }

            $shipmentMap = $this->fetchShipmentStatusMap();
            $this->applyShipmentStatuses($shipmentMap);

            if (! $backfill) {
                $this->settings->setOrdersPullWatermark(now());
            }
        } catch (\Throwable $e) {
            report($e);
            $errors[] = $e->getMessage();
        }

        return compact('created', 'updated', 'notified', 'errors');
    }

    public function pullSingleById(string $ananasOrderId): void
    {
        $payload = $this->apiClient->getOrdersPage(['orderId' => $ananasOrderId, 'size' => 1]);
        $rows = $this->apiClient->normalizeListPayload($payload);

        if ($rows === []) {
            return;
        }

        $order = $this->upsertOrderFromPayload($rows[0]);
        $map = $this->fetchShipmentStatusMapForOrder($ananasOrderId);

        if ($map !== []) {
            $this->applyShipmentStatuses($map);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function fetchAllOrderPages(array $query): array
    {
        $page = 0;
        $collected = [];

        do {
            $query['page'] = $page;
            $payload = $this->apiClient->getOrdersPage($query);
            $rows = $this->apiClient->normalizeListPayload($payload);
            $collected = array_merge($collected, $rows);
            $page++;
            $last = (bool) ($payload['last'] ?? true);
        } while ($rows !== [] && ! $last && $page < 50);

        return $collected;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function uniqueOrdersById(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $map[$id] = $row;
        }

        return $map;
    }

    /**
     * @return array<string, array{status: string, suborder_id: ?string}>
     */
    private function fetchShipmentStatusMap(): array
    {
        $map = [];

        foreach (['SG_FOR_PACKAGING', 'SG_SHIPMENT_ON_DELIVERY', 'SG_COMPLETED'] as $group) {
            $query = ['statusGroup' => $group, 'size' => 100, 'page' => 0];
            $payload = $this->apiClient->getOutboundShipmentsPage($query);
            $rows = $this->apiClient->normalizeListPayload($payload);

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $orderId = trim((string) ($row['orderId'] ?? ''));
                $status = strtoupper(trim((string) ($row['status'] ?? '')));

                if ($orderId === '' || $status === '') {
                    continue;
                }

                $map[$orderId] = [
                    'status' => $status,
                    'suborder_id' => isset($row['suborderId']) ? (string) $row['suborderId'] : null,
                ];
            }
        }

        return $map;
    }

    /**
     * @return array<string, array{status: string, suborder_id: ?string}>
     */
    private function fetchShipmentStatusMapForOrder(string $orderId): array
    {
        $payload = $this->apiClient->getOutboundShipmentsPage(['search' => $orderId, 'size' => 10, 'page' => 0]);
        $rows = $this->apiClient->normalizeListPayload($payload);
        $map = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['orderId'] ?? ''));

            if ($id === '') {
                continue;
            }

            $map[$id] = [
                'status' => strtoupper(trim((string) ($row['status'] ?? ''))),
                'suborder_id' => isset($row['suborderId']) ? (string) $row['suborderId'] : null,
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{status: string, suborder_id: ?string}>  $map
     */
    private function applyShipmentStatuses(array $map): void
    {
        foreach ($map as $orderId => $info) {
            /** @var AnanasOrder|null $order */
            $order = AnanasOrder::query()->where('ananas_order_id', $orderId)->first();

            if ($order === null) {
                continue;
            }

            $status = $info['status'];
            $order->remote_status = $status;
            $order->suborder_id = $info['suborder_id'] ?? $order->suborder_id;
            $order->status_bucket = AnanasOrderStatus::bucket($status);
            $order->is_terminal = AnanasOrderStatus::isTerminal($status);
            $order->save();

            foreach ($order->items as $item) {
                $this->committedStock->refreshItemCommitment($item, $order->is_terminal);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertOrderFromPayload(array $payload): AnanasOrder
    {
        $orderId = trim((string) ($payload['id'] ?? ''));

        return DB::transaction(function () use ($payload, $orderId): AnanasOrder {
            $methods = $payload['paymentMethods'] ?? $payload['paymentMethod'] ?? [];
            $methods = is_array($methods) ? array_values(array_filter($methods, is_string(...))) : [];

            $createdAt = isset($payload['createdDate'])
                ? Carbon::parse((string) $payload['createdDate'])
                : null;

            $awaiting = $this->orderAwaitingConfirmation($payload);

            /** @var AnanasOrder $order */
            $order = AnanasOrder::query()->firstOrNew(['ananas_order_id' => $orderId]);
            $wasNew = ! $order->exists;

            $order->fill([
                'remote_environment' => $this->settings->environment(),
                'ananas_created_at' => $createdAt,
                'total_price' => (float) ($payload['totalPrice'] ?? 0),
                'currency' => (string) ($payload['currency'] ?? 'BAM'),
                'payment_methods' => $methods,
                'billing_address' => is_array($payload['billingAddress'] ?? null) ? $payload['billingAddress'] : null,
            ]);

            if ($wasNew || $order->remote_status === null) {
                $order->remote_status = $awaiting ? 'CREATED' : $order->remote_status;
                $order->status_bucket = AnanasOrderStatus::bucket($order->remote_status, $awaiting);
                $order->is_terminal = AnanasOrderStatus::isTerminal($order->remote_status);
            }

            $order->save();

            $this->syncItems($order, is_array($payload['items'] ?? null) ? $payload['items'] : []);

            return $order->fresh(['items']);
        });
    }

    /**
     * @param  list<mixed>  $items
     */
    private function syncItems(AnanasOrder $order, array $items): void
    {
        foreach ($items as $itemPayload) {
            if (! is_array($itemPayload)) {
                continue;
            }

            $lineId = trim((string) ($itemPayload['id'] ?? ''));

            if ($lineId === '') {
                continue;
            }

            $mapping = $this->resolveMapping($itemPayload);
            $merchantId = $mapping?->inventoryId() ?? 0;

            /** @var AnanasOrderItem $item */
            $item = AnanasOrderItem::query()->firstOrNew([
                'ananas_order_id' => $order->id,
                'ananas_line_id' => $lineId,
            ]);

            $item->fill([
                'product_id' => $mapping?->product_id,
                'ananas_product_mapping_id' => $mapping?->id,
                'merchant_inventory_id' => $merchantId > 0 ? $merchantId : null,
                'product_ean' => (string) ($itemPayload['productEan'] ?? ''),
                'product_sku' => (string) ($itemPayload['productSku'] ?? ''),
                'product_name' => (string) ($itemPayload['productName'] ?? ''),
                'quantity_ordered' => max(0, (int) ($itemPayload['quantity'] ?? 0)),
                'quantity_confirmed' => isset($itemPayload['confirmedQuantity']) && $itemPayload['confirmedQuantity'] !== null
                    ? max(0, (int) $itemPayload['confirmedQuantity'])
                    : null,
                'quantity_packed' => isset($itemPayload['packedQuantity']) && $itemPayload['packedQuantity'] !== null
                    ? max(0, (int) $itemPayload['packedQuantity'])
                    : null,
                'unit_price' => (float) ($itemPayload['basePrice'] ?? $itemPayload['chargedPrice'] ?? 0),
                'line_total' => (float) ($itemPayload['grandTotal'] ?? $itemPayload['chargedPrice'] ?? 0),
                'vat' => isset($itemPayload['vat']) ? (float) $itemPayload['vat'] : null,
            ]);

            $item->save();

            $this->committedStock->refreshItemCommitment($item, $order->is_terminal);
        }
    }

    /**
     * @param  array<string, mixed>  $itemPayload
     */
    private function resolveMapping(array $itemPayload): ?AnanasProductMapping
    {
        $ean = trim((string) ($itemPayload['productEan'] ?? ''));

        if ($ean === '') {
            return null;
        }

        return AnanasProductMapping::query()
            ->forCurrentRemoteEnvironment()
            ->where('ean', $ean)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderAwaitingConfirmation(array $payload): bool
    {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! array_key_exists('confirmedQuantity', $item) || $item['confirmedQuantity'] === null) {
                return true;
            }
        }

        return $items === [];
    }

    private function shouldNotify(AnanasOrder $order): bool
    {
        if ($order->notified_at !== null) {
            return false;
        }

        $since = $this->settings->ordersNotifySince();

        if ($since === null) {
            return false;
        }

        if ($order->ananas_created_at === null) {
            return true;
        }

        return $order->ananas_created_at->greaterThanOrEqualTo($since);
    }

    private function sendNotification(AnanasOrder $order): void
    {
        $order->loadMissing('items');

        foreach (OrderNotificationMail::recipients() as $recipient) {
            Mail::to($recipient)->queue(new AnanasOrderNotificationAdmin($order));
        }
    }
}
