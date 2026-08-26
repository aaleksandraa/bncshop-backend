<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_payload_includes_product_listing_flag(): void
    {
        CmsPage::query()->create([
            'title' => 'Polovni laptopi',
            'slug' => 'polovni-laptopi',
            'status' => 'active',
            'has_product_listing' => true,
        ]);

        $this->getJson('/api/v1/pages/polovni-laptopi')
            ->assertOk()
            ->assertJsonPath('data.slug', 'polovni-laptopi')
            ->assertJsonPath('data.has_product_listing', true);
    }

    public function test_products_listing_filters_by_cms_page_slug(): void
    {
        $page = CmsPage::query()->create([
            'title' => 'Polovni laptopi',
            'slug' => 'polovni-laptopi',
            'status' => 'active',
            'has_product_listing' => true,
        ]);

        $included = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $page->products()->attach($included->id);

        $response = $this->getJson('/api/v1/products?cms_page=polovni-laptopi');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.pagination.total'));
        $this->assertSame($included->id, $response->json('data.0.id'));
        $this->assertSame([], $response->json('data.0.campaign_badges') ?? []);
    }

    public function test_products_listing_is_empty_when_page_listing_disabled(): void
    {
        $page = CmsPage::query()->create([
            'title' => 'O nama',
            'slug' => 'o-nama-test',
            'status' => 'active',
            'has_product_listing' => false,
        ]);

        $product = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $page->products()->attach($product->id);

        $this->getJson('/api/v1/products?cms_page=o-nama-test')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_category_options_respects_cms_page_filter(): void
    {
        $category = Category::factory()->create([
            'full_slug' => 'it-oprema/laptopi',
        ]);
        $otherCategory = Category::factory()->create([
            'full_slug' => 'it-oprema/monitori',
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

        $page = CmsPage::query()->create([
            'title' => 'Odabrani',
            'slug' => 'odabrani',
            'status' => 'active',
            'has_product_listing' => true,
        ]);
        $page->products()->attach($included->id);

        $this->getJson('/api/v1/products/category-options?cms_page=odabrani')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', 'it-oprema/laptopi');
    }
}
