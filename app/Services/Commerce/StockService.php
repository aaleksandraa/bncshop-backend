<?php

namespace App\Services\Commerce;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function reserve(Product $product, int $quantity): void
    {
        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! $this->canFulfill($locked, $quantity)) {
                throw new RuntimeException("Nedovoljna zaliha za proizvod {$locked->name}.");
            }

            $locked->reserved_stock += $quantity;
            $this->recalculateAvailable($locked);
            $locked->save();
        });
    }

    public function release(Product $product, int $quantity): void
    {
        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $locked->reserved_stock = max(0, $locked->reserved_stock - $quantity);
            $this->recalculateAvailable($locked);
            $locked->save();
        });
    }

    public function deduct(Product $product, int $quantity): void
    {
        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->manual_stock_override !== null) {
                $locked->manual_stock_override = max(0, $locked->manual_stock_override - $quantity);
            } else {
                $locked->api_stock = max(0, $locked->api_stock - $quantity);
            }

            $locked->reserved_stock = max(0, $locked->reserved_stock - $quantity);
            $this->recalculateAvailable($locked);
            $locked->save();
        });
    }

    public function canFulfill(Product $product, int $quantity): bool
    {
        if ($product->allow_backorder) {
            return true;
        }

        return $product->available_stock >= $quantity;
    }

    private function recalculateAvailable(Product $product): void
    {
        $baseStock = $product->manual_stock_override ?? $product->api_stock;
        $product->available_stock = max(0, (int) $baseStock - (int) $product->reserved_stock);
        $product->syncStockStatus();
    }
}
