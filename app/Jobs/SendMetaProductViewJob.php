<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Integrations\MetaConversionsApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaProductViewJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $productId,
        public string $eventSourceUrl,
        public ?string $clientIp,
        public ?string $userAgent,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(MetaConversionsApi $api): void
    {
        $product = Product::query()->find($this->productId);

        if ($product === null) {
            return;
        }

        $api->sendProductView(
            $product,
            $this->eventSourceUrl,
            $this->clientIp,
            $this->userAgent,
        );
    }
}
