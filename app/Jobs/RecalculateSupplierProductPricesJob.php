<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateSupplierProductPricesJob implements ShouldQueue
{
    use Queueable;

    public const CHUNK_SIZE = 100;

    public int $timeout = 90;

    public int $tries = 3;

    public function __construct(
        public int $supplierId,
        public string $supplierLabel,
        public int $afterProductId = 0,
        public bool $flushCacheAfter = false,
    ) {
        $this->onQueue('default');
    }

    public static function start(int $supplierId, string $supplierLabel): int
    {
        $afterProductId = 0;
        $dispatched = 0;

        while (true) {
            $remaining = self::supplierProductsQuery($supplierId)
                ->where('products.id', '>', $afterProductId)
                ->count();

            if ($remaining === 0) {
                break;
            }

            $isFinalChunk = $remaining <= self::CHUNK_SIZE;

            self::dispatch(
                $supplierId,
                $supplierLabel,
                $afterProductId,
                $isFinalChunk,
            )->afterCommit();

            $dispatched++;

            if ($isFinalChunk) {
                break;
            }

            $afterProductId = (int) self::supplierProductsQuery($supplierId)
                ->where('products.id', '>', $afterProductId)
                ->orderBy('products.id')
                ->offset(self::CHUNK_SIZE - 1)
                ->value('products.id');
        }

        Log::info('Supplier product price recalculation jobs queued.', [
            'supplier_id' => $supplierId,
            'supplier_label' => $supplierLabel,
            'chunks_dispatched' => $dispatched,
        ]);

        return $dispatched;
    }

    public function handle(ProductPriceRecalculator $recalculator, ProductReadCache $productReadCache): void
    {
        $lastProcessedId = $this->afterProductId;
        $processed = $recalculator->forSupplierChunk(
            $this->supplierId,
            $this->afterProductId,
            self::CHUNK_SIZE,
            null,
            $lastProcessedId,
        );

        if ($this->flushCacheAfter) {
            $productReadCache->flushAll();
        }

        Log::info('Supplier product price recalculation chunk completed.', [
            'supplier_id' => $this->supplierId,
            'supplier_label' => $this->supplierLabel,
            'chunk_processed' => $processed,
            'after_product_id' => $this->afterProductId,
            'last_processed_product_id' => $lastProcessedId,
            'flush_cache' => $this->flushCacheAfter,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Supplier product price recalculation chunk failed.', [
            'supplier_id' => $this->supplierId,
            'supplier_label' => $this->supplierLabel,
            'after_product_id' => $this->afterProductId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private static function supplierProductsQuery(int $supplierId): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()
            ->where('price_locked', false)
            ->whereHas('supplierOffers', fn ($query) => $query->where('supplier_id', $supplierId));
    }
}
