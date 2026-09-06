<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Integrations\MetaConversionsApi;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Console\Command;

class ReplayMetaPurchaseEventsCommand extends Command
{
    protected $signature = 'meta:replay-purchases
        {--order= : Replay a single order by order_number}
        {--days=30 : Replay orders created in the last N days}
        {--hours= : Replay orders created in the last N hours (overrides --days)}
        {--all : Replay all orders regardless of age}
        {--force : Skip confirmation when using --all}';

    protected $description = 'Replay Meta CAPI Purchase events for past orders';

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
        } elseif ($this->option('all')) {
            if (! $this->option('force') && ! $this->confirm('Poslati Meta Purchase za SVE narudžbe u bazi?', false)) {
                $this->warn('Prekinuto.');

                return self::SUCCESS;
            }
        } elseif ($this->option('hours') !== null) {
            $hours = max(1, (int) $this->option('hours'));
            $query->where('created_at', '>=', now()->subHours($hours));
        } else {
            $days = max(1, (int) $this->option('days'));
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn('Nema narudžbi za replay.');

            return self::SUCCESS;
        }

        $this->info("Šaljem {$orders->count()} Meta Purchase događaj(a)...");

        $sent = 0;

        foreach ($orders as $order) {
            $api->sendPurchase($order);
            $sent++;
            $this->line("Sent Purchase for {$order->order_number} ({$order->created_at})");
        }

        $this->info("Replayed {$sent} Meta Purchase event(s).");

        return self::SUCCESS;
    }
}
