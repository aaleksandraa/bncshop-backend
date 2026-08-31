<?php

namespace App\Services\Ananas;

use App\Models\Product;
use RuntimeException;

class AnanasEligibilityPolicy
{
    public const REFURBISHED_OR_USED = 'REFURBISHED_OR_USED';

    public const SET_PRODUCT = 'SET_PRODUCT';

    public function evaluate(Product $product): AnanasEligibilityResult
    {
        if ((bool) $product->is_refurbished) {
            return AnanasEligibilityResult::notEligible(self::REFURBISHED_OR_USED);
        }

        if ((bool) $product->is_set) {
            return AnanasEligibilityResult::notEligible(self::SET_PRODUCT);
        }

        return AnanasEligibilityResult::eligible();
    }

    public function assertCanExport(Product $product): void
    {
        $result = $this->evaluate($product);

        if (! $result->eligible) {
            throw new RuntimeException(sprintf(
                'Product %d is not eligible for Ananas export: %s',
                $product->id,
                $result->reasonCode ?? 'NOT_ELIGIBLE',
            ));
        }
    }
}
