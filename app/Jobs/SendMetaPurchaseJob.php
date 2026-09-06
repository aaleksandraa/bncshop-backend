<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Integrations\MetaConversionsApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaPurchaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $orderId)
    {
        $this->onQueue('analytics');
    }

    public function handle(MetaConversionsApi $api): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        if ($order === null) {
            return;
        }

        $api->sendPurchase($order);
    }
}
