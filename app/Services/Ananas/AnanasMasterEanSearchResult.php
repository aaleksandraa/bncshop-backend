<?php

namespace App\Services\Ananas;

use App\Models\Product;

class AnanasMasterEanSearchResult
{
    public function __construct(
        public readonly ?Product $product,
        public readonly int $productsScanned = 0,
        public readonly int $eligibleCandidates = 0,
        public readonly int $eansChecked = 0,
        public readonly int $masterCatalogHits = 0,
    ) {}

    public function found(): bool
    {
        return $this->product !== null;
    }
}
