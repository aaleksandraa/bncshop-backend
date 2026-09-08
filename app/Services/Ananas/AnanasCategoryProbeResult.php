<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryProbe;

class AnanasCategoryProbeResult
{
    /**
     * @param  list<string>  $observedCategories
     */
    public function __construct(
        public readonly int $probeId,
        public readonly int $mappingId,
        public readonly int $productId,
        public readonly string $status,
        public readonly ?string $progressId = null,
        public readonly array $observedCategories = [],
        public readonly ?string $observedProductType = null,
        public readonly ?string $remoteProductId = null,
        public readonly ?string $message = null,
    ) {}

    public function isValidated(): bool
    {
        return $this->status === AnanasCategoryProbe::STATUS_VALIDATED;
    }

    public function isPending(): bool
    {
        return $this->status === AnanasCategoryProbe::STATUS_PENDING;
    }
}
