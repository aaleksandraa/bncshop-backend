<?php

namespace App\Services\Commerce;

use App\Models\Product;
use App\Models\ProductSetItem;
use App\Services\Catalog\ProductSetService;
use App\Services\Pricing\ProductSalePriceService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public function __construct(
        private readonly ProductSetService $productSetService,
        private readonly ProductSalePriceService $productSalePriceService,
    ) {}

    public function reserve(Product $product, int $quantity): void
    {
        if ($product->isSet()) {
            $this->applyToSetComponents($product, $quantity, 'reserve');

            return;
        }

        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! $this->canFulfill($locked, $quantity)) {
                throw new RuntimeException("Nedovoljna zaliha za proizvod {$locked->name}.");
            }

            $locked->reserved_stock += $quantity;
            $this->recalculateAvailable($locked);
            $locked->save();

            $this->productSetService->refreshSetsContaining($locked);
        });
    }

    public function release(Product $product, int $quantity): void
    {
        if ($product->isSet()) {
            $this->applyToSetComponents($product, $quantity, 'release');

            return;
        }

        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $locked->reserved_stock = max(0, $locked->reserved_stock - $quantity);
            $this->recalculateAvailable($locked);
            $locked->save();

            $this->productSetService->refreshSetsContaining($locked);
        });
    }

    public function deduct(Product $product, int $quantity): void
    {
        if ($product->isSet()) {
            $this->applyToSetComponents($product, $quantity, 'deduct');

            return;
        }

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

            $this->productSetService->refreshSetsContaining($locked);
        });
    }

    public function canFulfill(Product $product, int $quantity): bool
    {
        if ($product->allow_backorder) {
            return true;
        }

        if ($product->isSet()) {
            return $this->productSetService->availableSetQuantity($product) >= $quantity;
        }

        return $product->available_stock >= $quantity;
    }

    public function restore(Product $product, int $quantity): void
    {
        if ($product->isSet()) {
            $this->applyToSetComponents($product, $quantity, 'restore');

            return;
        }

        DB::transaction(function () use ($product, $quantity): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->manual_stock_override !== null) {
                $locked->manual_stock_override += $quantity;
            } else {
                $locked->api_stock += $quantity;
            }

            $this->recalculateAvailable($locked);
            $locked->save();

            $this->productSetService->refreshSetsContaining($locked);
        });
    }

    private function applyToSetComponents(Product $setProduct, int $setQuantity, string $action): void
    {
        DB::transaction(function () use ($setProduct, $setQuantity, $action): void {
            $setProduct->loadMissing('setItems.componentProduct');

            if (! $this->canFulfill($setProduct, $setQuantity)) {
                throw new RuntimeException("Nedovoljna zaliha za set {$setProduct->name}.");
            }

            foreach ($setProduct->setItems as $item) {
                $component = $item->componentProduct;

                if ($component === null) {
                    continue;
                }

                $componentQuantity = $setQuantity * max(1, (int) $item->quantity);

                match ($action) {
                    'reserve' => $this->reserveComponent($component, $componentQuantity),
                    'release' => $this->releaseComponent($component, $componentQuantity),
                    'deduct' => $this->deductComponent($component, $componentQuantity),
                    'restore' => $this->restoreComponent($component, $componentQuantity),
                    default => throw new RuntimeException("Unknown stock action: {$action}"),
                };
            }

            $this->productSetService->refreshSetDerivedState($setProduct->fresh(['setItems.componentProduct']));
        });
    }

    private function reserveComponent(Product $product, int $quantity): void
    {
        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

        if (! $this->canFulfill($locked, $quantity)) {
            throw new RuntimeException("Nedovoljna zaliha za proizvod {$locked->name}.");
        }

        $locked->reserved_stock += $quantity;
        $this->recalculateAvailable($locked);
        $locked->save();

        $this->productSetService->refreshSetsContaining($locked);
    }

    private function releaseComponent(Product $product, int $quantity): void
    {
        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
        $locked->reserved_stock = max(0, $locked->reserved_stock - $quantity);
        $this->recalculateAvailable($locked);
        $locked->save();

        $this->productSetService->refreshSetsContaining($locked);
    }

    private function deductComponent(Product $product, int $quantity): void
    {
        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

        if ($locked->manual_stock_override !== null) {
            $locked->manual_stock_override = max(0, $locked->manual_stock_override - $quantity);
        } else {
            $locked->api_stock = max(0, $locked->api_stock - $quantity);
        }

        $locked->reserved_stock = max(0, $locked->reserved_stock - $quantity);
        $this->recalculateAvailable($locked);
        $locked->save();

        $this->productSetService->refreshSetsContaining($locked);
    }

    private function restoreComponent(Product $product, int $quantity): void
    {
        $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

        if ($locked->manual_stock_override !== null) {
            $locked->manual_stock_override += $quantity;
        } else {
            $locked->api_stock += $quantity;
        }

        $this->recalculateAvailable($locked);
        $locked->save();

        $this->productSetService->refreshSetsContaining($locked);
    }

    private function recalculateAvailable(Product $product): void
    {
        $baseStock = $product->manual_stock_override ?? $product->api_stock;
        $product->available_stock = max(0, (int) $baseStock - (int) $product->reserved_stock);
        $product->syncStockStatus();

        if ((int) $product->available_stock <= 0
            && $this->productSalePriceService->deactivateUntilStockSalesIfOutOfStock($product)) {
            app(\App\Services\Pricing\PriceCalculator::class)->recalculateAndPersist($product);
        }
    }
}
