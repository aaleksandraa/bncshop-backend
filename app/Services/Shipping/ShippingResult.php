<?php

namespace App\Services\Shipping;

use App\Models\ShippingRule;

readonly class ShippingResult
{
    public function __construct(
        public float $fee,
        public bool $isFree,
        public ?ShippingRule $rule = null,
        public array $snapshot = [],
    ) {}

    public function toArray(): array
    {
        return [
            'fee' => $this->fee,
            'is_free' => $this->isFree,
            'rule' => $this->snapshot,
        ];
    }
}
