<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Catalog\CategoryScopeResolver;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\DiscountEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountAndCouponScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_discount_applies_to_subcategories(): void
    {
        $parent = Category::factory()->create(['depth' => 0]);
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
        ]);

        $product = Product::factory()->create([
            'category_id' => $child->id,
            'regular_price' => 100,
            'api_final_price' => 100,
            'is_public' => true,
            'status' => 'active',
        ]);

        $discount = Discount::factory()->create([
            'type' => 'category',
            'discount_type' => 'percentage',
            'value' => 20,
            'category_id' => $parent->id,
            'include_subcategories' => true,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $discount->categories()->sync([$parent->id]);

        $best = app(DiscountEngine::class)->bestForProduct($product);

        $this->assertNotNull($best);
        $this->assertEquals(80.0, app(DiscountEngine::class)->applyDiscount($best, 100));
    }

    public function test_brand_discount_supports_multiple_manufacturers(): void
    {
        $hp = Manufacturer::factory()->create(['name' => 'HP', 'slug' => 'hp']);
        $dell = Manufacturer::factory()->create(['name' => 'Dell', 'slug' => 'dell']);

        $product = Product::factory()->create([
            'manufacturer_id' => $dell->id,
            'regular_price' => 200,
            'api_final_price' => 200,
            'is_public' => true,
            'status' => 'active',
        ]);

        $discount = Discount::factory()->create([
            'type' => 'brand',
            'discount_type' => 'percentage',
            'value' => 10,
            'manufacturer_id' => $hp->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $discount->manufacturers()->sync([$hp->id, $dell->id]);

        $best = app(DiscountEngine::class)->bestForProduct($product);

        $this->assertNotNull($best);
        $this->assertEquals(180.0, app(DiscountEngine::class)->applyDiscount($best, 200));
    }

    public function test_coupon_applies_only_to_selected_brands(): void
    {
        $allowed = Manufacturer::factory()->create(['slug' => 'lenovo']);
        $other = Manufacturer::factory()->create(['slug' => 'acer']);

        $allowedProduct = Product::factory()->create(['manufacturer_id' => $allowed->id]);
        $otherProduct = Product::factory()->create(['manufacturer_id' => $other->id]);

        $coupon = Coupon::query()->create([
            'code' => 'BRAND10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'applicable_to' => [
                'scope' => 'brands',
                'manufacturer_ids' => [$allowed->id],
            ],
        ]);

        $engine = app(CouponEngine::class);

        $this->assertTrue($engine->isApplicableToProduct($coupon, $allowedProduct));
        $this->assertFalse($engine->isApplicableToProduct($coupon, $otherProduct));
        $this->assertEquals(90.0, $engine->apply(100, $coupon, $allowedProduct));
        $this->assertEquals(100.0, $engine->apply(100, $coupon, $otherProduct));
    }

    public function test_coupon_applies_only_to_selected_products(): void
    {
        $allowedProduct = Product::factory()->create([
            'regular_price' => 100,
            'api_final_price' => 100,
            'is_public' => true,
            'status' => 'active',
        ]);
        $otherProduct = Product::factory()->create([
            'regular_price' => 100,
            'api_final_price' => 100,
            'is_public' => true,
            'status' => 'active',
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'PROD10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'applicable_to' => [
                'scope' => 'products',
                'product_ids' => [$allowedProduct->id],
            ],
        ]);

        $engine = app(CouponEngine::class);

        $this->assertTrue($engine->isApplicableToProduct($coupon, $allowedProduct));
        $this->assertFalse($engine->isApplicableToProduct($coupon, $otherProduct));
        $this->assertEquals(90.0, $engine->apply(100, $coupon, $allowedProduct));
    }

    public function test_coupon_applies_only_to_selected_tags(): void
    {
        $tag = \App\Models\Tag::query()->create(['name' => 'Gaming', 'slug' => 'gaming']);
        $otherTag = \App\Models\Tag::query()->create(['name' => 'Office', 'slug' => 'office']);

        $taggedProduct = Product::factory()->create([
            'regular_price' => 200,
            'api_final_price' => 200,
            'is_public' => true,
            'status' => 'active',
        ]);
        $otherProduct = Product::factory()->create([
            'regular_price' => 200,
            'api_final_price' => 200,
            'is_public' => true,
            'status' => 'active',
        ]);

        $taggedProduct->tags()->attach($tag->id);
        $otherProduct->tags()->attach($otherTag->id);

        $coupon = Coupon::query()->create([
            'code' => 'TAG15',
            'type' => 'percentage',
            'value' => 15,
            'is_active' => true,
            'applicable_to' => [
                'scope' => 'tags',
                'tag_ids' => [$tag->id],
            ],
        ]);

        $engine = app(CouponEngine::class);

        $this->assertTrue($engine->isApplicableToProduct($coupon, $taggedProduct));
        $this->assertFalse($engine->isApplicableToProduct($coupon, $otherProduct));
    }

    public function test_validate_for_preview_skips_min_cart_amount(): void
    {
        $product = Product::factory()->create([
            'regular_price' => 50,
            'api_final_price' => 50,
            'is_public' => true,
            'status' => 'active',
        ]);

        Coupon::query()->create([
            'code' => 'MIN100',
            'type' => 'percentage',
            'value' => 10,
            'min_cart_amount' => 100,
            'is_active' => true,
        ]);

        $engine = app(CouponEngine::class);
        $preview = $engine->validateForPreview('MIN100', $product);

        $this->assertTrue($preview['valid']);
        $this->assertSame('MIN100', $preview['coupon']?->code);

        $full = $engine->validate('MIN100', 50, null, null);
        $this->assertFalse($full['valid']);
    }

    public function test_category_scope_resolver_includes_descendants(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);

        $product = Product::factory()->create(['category_id' => $grandchild->id]);

        $this->assertTrue(app(CategoryScopeResolver::class)->matchesAnyCategory(
            $product,
            [$parent->id],
            true,
        ));
    }
}
