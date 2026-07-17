<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductSupplierOffer;
use Illuminate\Support\Collection;

class SupplierOfferSelector
{
    public function select(Product $product): ?ProductSupplierOffer
    {
        $product->loadMissing('supplierOffers.supplier');

        /** @var Collection<int, ProductSupplierOffer> $offers */
        $offers = $product->supplierOffers;

        if ($offers->isEmpty()) {
            return null;
        }

        if ($product->preferred_supplier_id) {
            $preferred = $offers->firstWhere('supplier_id', $product->preferred_supplier_id);

            if ($preferred) {
                return $preferred;
            }
        }

        $selected = $offers->firstWhere('is_selected_price_source', true);

        if ($selected) {
            return $selected;
        }

        $inStock = $offers
            ->filter(fn (ProductSupplierOffer $offer): bool => (int) $offer->supplier_stock > 0)
            ->sortBy('supplier_price')
            ->first();

        if ($inStock) {
            return $inStock;
        }

        return $offers->sortBy('supplier_price')->first();
    }
}
