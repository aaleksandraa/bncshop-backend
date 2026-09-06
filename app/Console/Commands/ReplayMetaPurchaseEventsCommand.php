<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Integrations\MetaConversionsApi;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Console\Command;

class ReplayMetaPurchaseEventsCommand extends Command
{
    protected $signature = 'meta:replay-purchases
        {--hours=24 : Replay orders created in the last N hours}
        {--order= : Replay a single order by order_number}';

    protected $description = 'Replay Meta CAPI Purchase events for recent orders';

    public function handle(MetaConversionsApi $api, TrackingSettings $trackingSettings): int
    {
        if (! filled($trackingSettings->all()['fb_access_token'] ?? null)) {
            $this->error('Meta CAPI access token nije podešen u admin postavkama.');

            return self::FAILURE;
        }

        $orderNumber = trim((string) $this->option('order'));

        $query = Order::query()->with('items')->latest('id');

        if ($orderNumber !== '') {
            $query->where('order_number', $orderNumber);
        } else {
            $hours = max(1, (int) $this->option('hours'));
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn('Nema narudžbi za replay.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($orders as $order) {
            $api->sendPurchase($order);
            $sent++;
            $this->line("Sent Purchase for {$order->order_number}");
        }

        $this->info("Replayed {$sent} Meta Purchase event(s).");

        return self::SUCCESS;
    }
}
