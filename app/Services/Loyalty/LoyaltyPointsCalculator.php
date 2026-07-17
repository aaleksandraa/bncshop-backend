<?php

namespace App\Services\Loyalty;

use App\Models\Order;

class LoyaltyPointsCalculator
{
    public function __construct(
        private readonly LoyaltySettings $settings,
    ) {}

    public function earnPointsForOrder(Order $order): int
    {
        $base = max(0, (float) $order->subtotal - (float) $order->discount_total);
        $rate = (float) $this->settings->get('points_per_km', 1);

        return (int) floor($base * $rate);
    }
}
