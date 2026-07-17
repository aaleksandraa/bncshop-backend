<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bShippingTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_shipping_fee_applies_below_free_threshold(): void
    {
        config([
            'b2b.shipping.fixed_fee' => 12.0,
            'b2b.shipping.free_threshold' => 500.0,
        ]);

        [$user, $customer] = $this->createB2bUser('ship@test.test', 0);
        $this->seedCart($customer, regularPrice: 100, quantity: 2);
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/shipping-quote')
            ->assertOk()
            ->assertJsonPath('data.shipping_fee', 12)
            ->assertJsonPath('data.is_free', false)
            ->assertJsonPath('data.grand_total', 212);
    }

    public function test_shipping_is_free_above_threshold(): void
    {
        config([
            'b2b.shipping.fixed_fee' => 12.0,
            'b2b.shipping.free_threshold' => 500.0,
        ]);

        [$user, $customer] = $this->createB2bUser('ship-free@test.test', 0);
        $this->seedCart($customer, regularPrice: 300, quantity: 2);
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/shipping-quote')
            ->assertOk()
            ->assertJsonPath('data.shipping_fee', 0)
            ->assertJsonPath('data.is_free', true)
            ->assertJsonPath('data.grand_total', 600);
    }

    private function seedCart($customer, float $regularPrice, int $quantity): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Ship cat',
            'slug' => 'ship-cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Ship product',
            'slug' => 'ship-product',
            'regular_price' => $regularPrice,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $cart = B2bCart::query()->create([
            'b2b_customer_id' => $customer->id,
        ]);

        B2bCartItem::query()->create([
            'b2b_cart_id' => $cart->id,
            'b2b_product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
