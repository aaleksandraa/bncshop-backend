<?php

namespace App\Jobs;

use App\Services\Pricing\ProductPriceRecalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateSupplierProductPricesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $uniqueFor = 300;

    public function __construct(
        public int $supplierId,
        public string $supplierLabel,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'supplier-price-recalc-'.$this->supplierId;
    }

    public function handle(ProductPriceRecalculator $recalculator): void
    {
        $count = $recalculator->forSupplierAndCategory($this->supplierId);

        Log::info('Supplier product prices recalculated.', [
            'supplier_id' => $this->supplierId,
            'supplier_label' => $this->supplierLabel,
            'products_recalculated' => $count,
        ]);
    }
}
