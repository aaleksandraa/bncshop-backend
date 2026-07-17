<?php

namespace App\Services\B2b;

class B2bShippingCalculator
{
    /**
     * @return array{fee: float, is_free: bool, free_threshold: float|null}
     */
    public function calculate(float $orderTotal): array
    {
        $fixedFee = (float) config('b2b.shipping.fixed_fee', 0);
        $freeThreshold = config('b2b.shipping.free_threshold');

        if ($freeThreshold !== null && $orderTotal >= (float) $freeThreshold) {
            return [
                'fee' => 0.0,
                'is_free' => true,
                'free_threshold' => (float) $freeThreshold,
            ];
        }

        return [
            'fee' => round($fixedFee, 2),
            'is_free' => false,
            'free_threshold' => $freeThreshold !== null ? (float) $freeThreshold : null,
        ];
    }
}
