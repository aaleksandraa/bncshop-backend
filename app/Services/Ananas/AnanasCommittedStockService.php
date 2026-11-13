<?php

namespace App\Services\Ananas;

use App\Models\AnanasOrderItem;
use App\Models\Product;

class AnanasCommittedStockService
{
    public function openQuantityForProduct(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        if (! $this->settings()->isOrdersPullEnabled()) {
            return 0;
        }

        return (int) AnanasOrderItem::query()
            ->where('product_id', $productId)
            ->where('committed_quantity', '>', 0)
            ->sum('committed_quantity');
    }

    public function effectiveAvailableStock(Product $product): int
    {
        $base = max(0, (int) $product->available_stock);
        $open = $this->openQuantityForProduct((int) $product->id);

        return max(0, $base - $open);
    }

    public function effectiveStockLevelForAnanas(Product $product): int
    {
        return $this->effectiveAvailableStock($product);
    }

    public function refreshItemCommitment(AnanasOrderItem $item, bool $orderTerminal): void
    {
        if (! $this->settings()->isOrdersPullEnabled()) {
            if ((int) $item->committed_quantity !== 0) {
                $item->committed_quantity = 0;
                $item->save();
            }

            return;
        }

        if ($item->product_id === null) {
            $item->committed_quantity = 0;
            $item->save();

            return;
        }

        if ($orderTerminal) {
            $item->committed_quantity = 0;
            $item->save();

            return;
        }

        $confirmed = $item->quantity_confirmed;
        $qty = $confirmed !== null && $confirmed > 0
            ? (int) $confirmed
            : max(0, (int) $item->quantity_ordered);

        $item->committed_quantity = $qty;
        $item->save();
    }

    private function settings(): AnanasSyncSettings
    {
        return app(AnanasSyncSettings::class);
    }
}
