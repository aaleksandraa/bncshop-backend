<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\Loyalty\LoyaltyPointsCalculator;
use App\Services\Loyalty\LoyaltySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_earn_points_uses_subtotal_minus_discount_without_shipping(): void
    {
        SystemSetting::query()->create([
            'key' => 'loyalty',
            'value' => ['points_per_km' => 1],
            'group' => 'loyalty',
        ]);

        $order = new Order([
            'subtotal' => 150,
            'discount_total' => 20,
            'shipping_fee' => 10,
        ]);

        $calculator = new LoyaltyPointsCalculator(new LoyaltySettings());

        $this->assertSame(130, $calculator->earnPointsForOrder($order));
    }
}
