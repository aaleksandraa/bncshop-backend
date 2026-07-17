<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_includes_coupon_preview(): void
    {
        $product = Product::factory()->create([
            'slug' => 'test-laptop',
            'regular_price' => 1000,
            'display_price' => 1000,
            'api_final_price' => 1000,
            'is_public' => true,
            'status' => 'active',
        ]);

        Coupon::query()->create([
            'code' => 'LETO20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'applicable_to' => [
                'scope' => 'products',
                'product_ids' => [$product->id],
            ],
        ]);

        $response = $this->getJson('/api/v1/products/test-laptop?kupon=LETO20');

        $response->assertOk()
            ->assertJsonPath('data.coupon.code', 'LETO20')
            ->assertJsonPath('data.coupon.applicable', true)
            ->assertJsonPath('data.coupon.price', 800);
    }

    public function test_apply_coupon_stores_pending_code_for_empty_cart(): void
    {
        Coupon::query()->create([
            'code' => 'PENDING10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/cart/coupon', [
            'code' => 'PENDING10',
        ], [
            'X-Cart-Session' => 'test-session-pending',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pending', true)
            ->assertJsonPath('data.coupon.code', 'PENDING10');

        $this->assertDatabaseHas('carts', [
            'session_id' => 'test-session-pending',
            'pending_coupon_code' => 'PENDING10',
            'coupon_code' => null,
        ]);
    }
}
