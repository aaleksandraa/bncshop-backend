<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateAllProductPricesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const CHUNK_SIZE = 100;

    public int $timeout = 90;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $afterProductId = 0,
        public bool $flushCacheAfter = false,
        public bool $chainNext = false,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return "catalog-price-recalc:{$this->afterProductId}:".($this->chainNext ? 'chain' : 'once');
    }

    public static function start(): int
    {
        $remaining = self::catalogQuery()->count();

        if ($remaining === 0) {
            return 0;
        }

        self::dispatch(0, false, true);

        $chunks = (int) ceil($remaining / self::CHUNK_SIZE);

        Log::info('Catalog product price recalculation queued.', [
            'remaining_products' => $remaining,
            'estimated_chunks' => $chunks,
        ]);

        return $chunks;
    }

    public function handle(ProductPriceRecalculator $recalculator, ProductReadCache $productReadCache): void
    {
        $lastProcessedId = $this->afterProductId;
        $processed = $recalculator->forCatalogChunk(
            $this->afterProductId,
            self::CHUNK_SIZE,
            $lastProcessedId,
        );

        Log::info('Catalog product price recalculation chunk completed.', [
            'chunk_processed' => $processed,
            'after_product_id' => $this->afterProductId,
            'last_processed_product_id' => $lastProcessedId,
            'chain_next' => $this->chainNext,
        ]);

        if (
            $this->chainNext
            && $lastProcessedId > $this->afterProductId
            && self::catalogQuery()->where('products.id', '>', $lastProcessedId)->exists()
        ) {
            self::dispatch($lastProcessedId, false, true);

            return;
        }

        if ($this->flushCacheAfter || $this->chainNext) {
            $productReadCache->flushAll();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Catalog product price recalculation chunk failed.', [
            'after_product_id' => $this->afterProductId,
            'chain_next' => $this->chainNext,
            'error' => $exception?->getMessage(),
        ]);
    }

    private static function catalogQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->notFromEline()
            ->where('is_set', false);
    }
}
