<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReindexProductsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  array<int, int>|null  $productIds
     */
    public function __construct(
        public ?array $productIds = null,
    ) {
        $this->onQueue('scout');
    }

    public function handle(): void
    {
        if ($this->productIds !== null && $this->productIds !== []) {
            Product::query()
                ->whereIn('id', $this->productIds)
                ->searchable();

            return;
        }

        Product::makeAllSearchable();
    }
}
