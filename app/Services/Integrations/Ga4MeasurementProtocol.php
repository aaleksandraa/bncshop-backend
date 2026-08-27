<?php

namespace App\Services\Integrations;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;
use Throwable;

class Ga4MeasurementProtocol
{
    public function __construct(
        private readonly TrackingSettings $trackingSettings,
    ) {}

    public function sendPurchase(Order $order): void
    {
        $settings = $this->trackingSettings->all();
        $measurementId = trim((string) ($settings['ga_measurement_id'] ?? ''));
        $apiSecret = trim((string) ($settings['ga_api_secret'] ?? ''));

        if ($measurementId === '' || $apiSecret === '') {
            return;
        }

        $order->loadMissing('items');

        $payload = [
            'client_id' => 'server.'.$order->id,
            'events' => [[
                'name' => 'purchase',
                'params' => [
                    'engagement_time_msec' => 1,
                    'transaction_id' => $order->order_number,
                    'currency' => 'BAM',
                    'value' => (float) $order->total,
                    'shipping' => (float) $order->shipping_fee,
                    'items' => $order->items
                        ->map(fn (OrderItem $item): array => [
                            'item_id' => (string) ($item->sku ?: $item->product_id ?: $item->id),
                            'item_name' => (string) $item->product_name,
                            'price' => (float) $item->unit_price,
                            'quantity' => (int) $item->quantity,
                        ])
                        ->values()
                        ->all(),
                ],
            ]],
        ];

        try {
            Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post($this->collectUrl($measurementId, $apiSecret), $payload)
                ->throw();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function collectUrl(string $measurementId, string $apiSecret): string
    {
        return 'https://www.google-analytics.com/mp/collect?'.http_build_query([
            'measurement_id' => $measurementId,
            'api_secret' => $apiSecret,
        ]);
    }
}
