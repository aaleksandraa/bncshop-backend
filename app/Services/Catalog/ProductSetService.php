<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductSetItem;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductSetService
{
    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    /**
     * @param  list<array{component_product_id: int, quantity: int}>  $items
     */
    public function syncSetItems(Product $setProduct, array $items): void
    {
        $this->validateSetItems($setProduct, $items);

        $setProduct->setItems()->delete();

        foreach ($items as $index => $item) {
            ProductSetItem::query()->create([
                'set_product_id' => $setProduct->id,
                'component_product_id' => (int) $item['component_product_id'],
                'quantity' => max(1, (int) $item['quantity']),
                'sort_order' => $index,
            ]);
        }

        $this->refreshSetDerivedState($setProduct->fresh(['setItems.componentProduct']));
    }

    /**
     * @param  list<array{component_product_id: int, quantity: int}>  $items
     */
    public function validateSetItems(Product $setProduct, array $items): void
    {
        if (count($items) < 2) {
            throw ValidationException::withMessages([
                'set_items' => 'Set mora sadržavati najmanje 2 proizvoda.',
            ]);
        }

        $componentIds = collect($items)->pluck('component_product_id')->map(fn ($id) => (int) $id);

        if ($componentIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'set_items' => 'Isti proizvod se ne može dodati više puta — povećajte količinu.',
            ]);
        }

        if ($setProduct->exists && $componentIds->contains($setProduct->id)) {
            throw ValidationException::withMessages([
                'set_items' => 'Set ne može sadržavati sam sebe.',
            ]);
        }

        $components = Product::query()
            ->whereIn('id', $componentIds->all())
            ->get()
            ->keyBy('id');

        foreach ($componentIds as $componentId) {
            if (! $components->has($componentId)) {
                throw ValidationException::withMessages([
                    'set_items' => "Proizvod #{$componentId} ne postoji.",
                ]);
            }

            if ($components->get($componentId)?->isSet()) {
                throw ValidationException::withMessages([
                    'set_items' => 'U set se ne mogu dodati drugi setovi.',
                ]);
            }
        }
    }

    public function componentsSum(Product $setProduct): float
    {
        $setProduct->loadMissing('setItems.componentProduct');

        return round(
            $setProduct->setItems->sum(function (ProductSetItem $item): float {
                $component = $item->componentProduct;

                if ($component === null) {
                    return 0.0;
                }

                return (float) $component->display_price * (int) $item->quantity;
            }),
            2,
        );
    }

    public function availableSetQuantity(Product $setProduct): int
    {
        $setProduct->loadMissing('setItems.componentProduct');

        if ($setProduct->setItems->isEmpty()) {
            return 0;
        }

        $limits = $setProduct->setItems->map(function (ProductSetItem $item): int {
            $component = $item->componentProduct;

            if ($component === null) {
                return 0;
            }

            if ($component->allow_backorder) {
                return PHP_INT_MAX;
            }

            $required = max(1, (int) $item->quantity);

            return (int) floor((int) $component->available_stock / $required);
        });

        return max(0, (int) $limits->min());
    }

    public function refreshSetDerivedState(Product $setProduct): void
    {
        if (! $setProduct->isSet()) {
            return;
        }

        $setProduct->loadMissing('setItems.componentProduct');

        $available = $this->availableSetQuantity($setProduct);
        $componentsSum = $this->componentsSum($setProduct);
        $setPrice = (float) ($setProduct->manual_price ?? $setProduct->display_price ?? 0);

        $setProduct->update([
            'regular_price' => $componentsSum,
            'display_price' => $setPrice,
            'on_sale' => $setPrice > 0 && $setPrice < $componentsSum,
            'price_locked' => true,
            'available_stock' => $available,
            'stock_status' => $available > 0 ? 'in_stock' : 'out_of_stock',
        ]);

        $this->productReadCache->forgetProduct($setProduct);
    }

    public function refreshSetsContaining(Product $componentProduct): void
    {
        $componentProduct->loadMissing('parentSets.setProduct.setItems.componentProduct');

        foreach ($componentProduct->parentSets as $parentItem) {
            $setProduct = $parentItem->setProduct;

            if ($setProduct === null || ! $setProduct->isSet()) {
                continue;
            }

            $this->refreshSetDerivedState($setProduct->fresh(['setItems.componentProduct']));
        }
    }

    /**
     * @return Collection<int, ProductSetItem>
     */
    public function loadSetItemsForDisplay(Product $setProduct): Collection
    {
        return $setProduct->setItems()
            ->with(['componentProduct.defaultImage', 'componentProduct.manufacturer'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshotForOrder(Product $setProduct): array
    {
        return $this->loadSetItemsForDisplay($setProduct)
            ->map(function (ProductSetItem $item): array {
                $component = $item->componentProduct;

                return [
                    'product_id' => $component?->id,
                    'name' => $component?->name,
                    'slug' => $component?->slug,
                    'sku' => $component?->sku,
                    'quantity' => (int) $item->quantity,
                    'unit_display_price' => $component !== null ? (float) $component->display_price : null,
                ];
            })
            ->values()
            ->all();
    }
}
