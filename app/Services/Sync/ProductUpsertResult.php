<?php

namespace App\Services\Sync;

use App\Models\Product;

readonly class ProductUpsertResult
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public string $action,
        public Product $product,
        public array $changedFields = [],
    ) {}

    public function isInserted(): bool
    {
        return $this->action === 'inserted';
    }

    public function isUpdated(): bool
    {
        return $this->action === 'updated';
    }

    public function isDeactivated(): bool
    {
        return $this->action === 'deactivated';
    }
}
