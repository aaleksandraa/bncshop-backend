<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasMasterEanSearchResult
{
    /**
     * @param  list<Product>  $products
     */
    public function __construct(
        public readonly ?Product $product,
        public readonly int $productsScanned = 0,
        public readonly int $eligibleCandidates = 0,
        public readonly int $eansChecked = 0,
        public readonly int $masterCatalogHits = 0,
        public readonly array $products = [],
    ) {}

    public function found(): bool
    {
        return $this->product !== null || $this->products !== [];
    }
}
