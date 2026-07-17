<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ShippingRule;
use App\Services\Commerce\CartService;
use App\Services\Pricing\DiscountEngine;
use App\Services\Pricing\PriceCalculator;
use App\Services\Shipping\ShippingCalculator;
use App\Services\Sync\AttributeNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BncPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_normalizer_handles_bosnian_boolean_values(): void
    {
        $normalizer = new AttributeNormalizer;

        $this->assertSame('true', $normalizer->normalize('Da')['normalized_value']);
        $this->assertSame('false', $normalizer->normalize('Ne')['normalized_value']);
        $this->assertSame('true', $normalizer->normalize('true')['normalized_value']);
    }

    public function test_manual_locked_price_overrides_api_price(): void
    {
        $product = Product::factory()->create([
            'price_locked' => true,
            'manual_price' => 99.99,
            'api_final_price' => 500,
            'regular_price' => 500,
        ]);

        $result = app(PriceCalculator::class)->calculate($product);

        $this->assertEquals(99.99, $result->displayPrice);
    }

    public function test_category_discount_applies_to_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'regular_price' => 100,
            'api_final_price' => 100,
            'is_public' => true,
            'status' => 'active',
        ]);

        Discount::factory()->create([
            'type' => 'category',
            'discount_type' => 'percentage',
            'value' => 10,
            'category_id' => $category->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $discount = app(DiscountEngine::class)->bestForProduct($product);

        $this->assertNotNull($discount);
        $this->assertEquals(90.0, app(DiscountEngine::class)->applyDiscount($discount, 100));
    }

    public function test_shipping_free_above_threshold(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 10,
            'free_threshold' => 100,
            'is_active' => true,
            'priority' => 0,
        ]);

        $product = Product::factory()->create([
            'display_price' => 50,
            'regular_price' => 50,
            'is_public' => true,
            'status' => 'active',
            'available_stock' => 10,
        ]);

        $cart = app(CartService::class)->getOrCreate('test-session');
        app(CartService::class)->addItem($cart, $product, 2);

        $result = app(ShippingCalculator::class)->calculate($cart, 'delivery');

        $this->assertEquals(0, $result->fee);
    }

    public function test_category_shipping_override_uses_higher_fee(): void
    {
        ShippingRule::factory()->create([
            'type' => 'global',
            'fixed_fee' => 10,
            'free_threshold' => 9999,
            'is_active' => true,
            'priority' => 0,
        ]);

        $category = Category::factory()->create();
        ShippingRule::factory()->create([
            'type' => 'category',
            'category_id' => $category->id,
            'fixed_fee' => 25,
            'free_threshold' => 9999,
            'is_active' => true,
            'priority' => 10,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'display_price' => 200,
            'regular_price' => 200,
            'available_stock' => 5,
            'is_public' => true,
            'status' => 'active',
        ]);

        $cart = app(CartService::class)->getOrCreate('test-session-2');
        app(CartService::class)->addItem($cart, $product, 1);

        $result = app(ShippingCalculator::class)->calculate($cart, 'delivery');

        $this->assertEquals(25, $result->fee);
    }
}
