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
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return "catalog-price-recalc:{$this->afterProductId}";
    }

    public static function start(): int
    {
        $afterProductId = 0;
        $dispatched = 0;

        while (true) {
            $remaining = self::catalogQuery()
                ->where('products.id', '>', $afterProductId)
                ->count();

            if ($remaining === 0) {
                break;
            }

            $isFinalChunk = $remaining <= self::CHUNK_SIZE;

            self::dispatch($afterProductId, $isFinalChunk)->afterCommit();

            $dispatched++;

            if ($isFinalChunk) {
                break;
            }

            $afterProductId = (int) self::catalogQuery()
                ->where('products.id', '>', $afterProductId)
                ->orderBy('products.id')
                ->offset(self::CHUNK_SIZE - 1)
                ->value('products.id');
        }

        Log::info('Catalog product price recalculation jobs queued.', [
            'chunks_dispatched' => $dispatched,
        ]);

        return $dispatched;
    }

    public function handle(ProductPriceRecalculator $recalculator, ProductReadCache $productReadCache): void
    {
        $lastProcessedId = $this->afterProductId;
        $processed = $recalculator->forCatalogChunk(
            $this->afterProductId,
            self::CHUNK_SIZE,
            $lastProcessedId,
        );

        if ($this->flushCacheAfter) {
            $productReadCache->flushAll();
        }

        Log::info('Catalog product price recalculation chunk completed.', [
            'chunk_processed' => $processed,
            'after_product_id' => $this->afterProductId,
            'last_processed_product_id' => $lastProcessedId,
            'flush_cache' => $this->flushCacheAfter,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Catalog product price recalculation chunk failed.', [
            'after_product_id' => $this->afterProductId,
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
