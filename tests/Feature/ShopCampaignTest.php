<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShopCampaign;
use App\Services\Catalog\CampaignResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_currently_active_respects_schedule(): void
    {
        $active = ShopCampaign::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $inactive = ShopCampaign::factory()->inactive()->create();
        $expired = ShopCampaign::factory()->expired()->create();
        $scheduled = ShopCampaign::factory()->scheduled()->create();

        $this->assertTrue($active->isCurrentlyActive());
        $this->assertFalse($inactive->isCurrentlyActive());
        $this->assertFalse($expired->isCurrentlyActive());
        $this->assertFalse($scheduled->isCurrentlyActive());
    }

    public function test_campaign_matches_products_by_manual_selection(): void
    {
        $campaign = ShopCampaign::factory()->create([
            'slug' => 'back-to-school',
        ]);

        $included = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $excluded = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $campaign->products()->attach($included->id);

        $resolver = app(CampaignResolver::class);
        $resolver->invalidateCache();

        $this->assertTrue($resolver->matches($included, $campaign->fresh(['products', 'excludedProducts', 'categories'])));
        $this->assertFalse($resolver->matches($excluded, $campaign->fresh(['products', 'excludedProducts', 'categories'])));
    }

    public function test_campaign_matches_products_by_category_with_subcategories(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $campaign = ShopCampaign::factory()->byCategories()->create([
            'include_subcategories' => true,
        ]);
        $campaign->categories()->attach($parent->id);

        $included = Product::factory()->create([
            'category_id' => $child->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        $outside = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $resolver = app(CampaignResolver::class);
        $resolver->invalidateCache();
        $loaded = $campaign->fresh(['products', 'excludedProducts', 'categories']);

        $this->assertTrue($resolver->matches($included, $loaded));
        $this->assertFalse($resolver->matches($outside, $loaded));
    }

    public function test_campaign_excludes_products_from_category_scope(): void
    {
        $category = Category::factory()->create();

        $campaign = ShopCampaign::factory()->byCategories()->create();
        $campaign->categories()->attach($category->id);

        $included = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        $excluded = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $campaign->excludedProducts()->attach($excluded->id);

        $resolver = app(CampaignResolver::class);
        $resolver->invalidateCache();
        $loaded = $campaign->fresh(['products', 'excludedProducts', 'categories']);

        $this->assertTrue($resolver->matches($included, $loaded));
        $this->assertFalse($resolver->matches($excluded, $loaded));
    }

    public function test_products_listing_filters_by_campaign_slug(): void
    {
        $campaign = ShopCampaign::factory()->create([
            'slug' => 'back-to-school',
        ]);

        $included = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $campaign->products()->attach($included->id);
        app(CampaignResolver::class)->invalidateCache();

        $response = $this->getJson('/api/v1/products?campaign=back-to-school');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $included->slug)
            ->assertJsonPath('data.0.campaign_badges.0.slug', 'back-to-school');
    }

    public function test_campaign_endpoint_returns_landing_payload(): void
    {
        ShopCampaign::factory()->create([
            'slug' => 'back-to-school',
            'page_title' => 'Back to school',
            'page_description' => 'Posebna ponuda',
        ]);

        app(CampaignResolver::class)->invalidateCache();

        $this->getJson('/api/v1/campaigns/back-to-school')
            ->assertOk()
            ->assertJsonPath('data.slug', 'back-to-school')
            ->assertJsonPath('data.title', 'Back to school')
            ->assertJsonPath('data.description', 'Posebna ponuda');
    }

    public function test_campaign_endpoint_returns_404_when_inactive(): void
    {
        ShopCampaign::factory()->inactive()->create([
            'slug' => 'back-to-school',
        ]);

        app(CampaignResolver::class)->invalidateCache();

        $this->getJson('/api/v1/campaigns/back-to-school')
            ->assertNotFound();
    }

    public function test_campaign_endpoint_returns_404_without_landing_page(): void
    {
        ShopCampaign::factory()->withoutLandingPage()->create([
            'slug' => 'badge-only',
        ]);

        app(CampaignResolver::class)->invalidateCache();

        $this->getJson('/api/v1/campaigns/badge-only')
            ->assertNotFound();
    }

    public function test_category_options_respects_campaign_filter(): void
    {
        $category = Category::factory()->create([
            'full_slug' => 'skola/torbe',
        ]);
        $otherCategory = Category::factory()->create([
            'full_slug' => 'it-oprema/laptopi',
        ]);

        $campaign = ShopCampaign::factory()->create([
            'slug' => 'back-to-school',
        ]);

        $included = Product::factory()->create([
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        Product::factory()->create([
            'category_id' => $otherCategory->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $campaign->products()->attach($included->id);
        app(CampaignResolver::class)->invalidateCache();

        $this->getJson('/api/v1/products/category-options?campaign=back-to-school')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', 'skola/torbe');
    }
}
