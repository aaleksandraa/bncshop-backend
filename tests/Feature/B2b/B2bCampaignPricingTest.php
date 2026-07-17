<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCampaign;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use App\Services\B2b\B2bPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bCampaignPricingTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_active_campaign_applies_percent_discount(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product',
            'regular_price' => 200,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $campaign = B2bCampaign::query()->create([
            'name' => 'Promo',
            'discount_type' => 'percent',
            'value' => 25,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'is_active' => true,
            'badge_text' => 'Promo',
        ]);

        $product->campaigns()->attach($campaign->id);
        $product->load('campaigns');

        [, $customer] = $this->createB2bUser('campaign@test.test', 10);

        $pricing = app(B2bPricingService::class)->calculate($product, $customer);

        $this->assertSame(150.0, $pricing['final_price']);
        $this->assertSame('Promo', $pricing['badge_text']);
    }

    public function test_expired_campaign_is_ignored(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat-expired',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-expired',
            'regular_price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $campaign = B2bCampaign::query()->create([
            'name' => 'Old promo',
            'discount_type' => 'percent',
            'value' => 50,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $product->campaigns()->attach($campaign->id);
        $product->load('campaigns');

        [, $customer] = $this->createB2bUser('expired@test.test', 0);

        $pricing = app(B2bPricingService::class)->calculate($product, $customer);

        $this->assertSame(100.0, $pricing['final_price']);
    }
}
