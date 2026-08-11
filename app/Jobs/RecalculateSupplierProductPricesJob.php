<?php

namespace App\Jobs;

use App\Services\Catalog\ProductReadCache;
use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateSupplierProductPricesJob implements ShouldQueue
{
    use Queueable;

    public const CHUNK_SIZE = 200;

    public int $timeout = 300;

    public function __construct(
        public int $supplierId,
        public string $supplierLabel,
        public int $afterProductId = 0,
    ) {
        $this->onQueue('default');
    }

    public static function start(int $supplierId, string $supplierLabel): void
    {
        self::dispatch($supplierId, $supplierLabel, 0)->afterCommit();
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

        if ($processed === self::CHUNK_SIZE) {
            self::dispatch($this->supplierId, $this->supplierLabel, $lastProcessedId);

            Log::info('Supplier product price recalculation chunk completed.', [
                'supplier_id' => $this->supplierId,
                'supplier_label' => $this->supplierLabel,
                'chunk_processed' => $processed,
                'continues_after_product_id' => $lastProcessedId,
            ]);

            return;
        }

        $productReadCache->flushAll();

        Log::info('Supplier product prices recalculated.', [
            'supplier_id' => $this->supplierId,
            'supplier_label' => $this->supplierLabel,
            'final_chunk_processed' => $processed,
            'completed_after_product_id' => $lastProcessedId,
        ]);
    }
}
