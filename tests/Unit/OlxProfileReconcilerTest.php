<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\OlxCategoryMapping;
use App\Models\Product;
use App\Services\Olx\OlxApiClient;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxProfileReconciler;
use App\Services\Olx\OlxSyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OlxProfileReconcilerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_deletes_remote_listings_without_a_shop_product(): void
    {
        $client = Mockery::mock(OlxApiClient::class);
        $client->shouldReceive('listShopListingIds')->once()->with('shop')->andReturn([101, 202]);

        $settings = Mockery::mock(OlxSyncSettings::class);
        $settings->shouldReceive('credentials')->andReturn(['username' => 'shop']);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('isEligible')->never();

        $plan = (new OlxProfileReconciler($client, $settings, $scope))->plan();

        $this->assertSame([], $plan['delete_product_ids']);
        $this->assertSame([101, 202], $plan['delete_listing_ids']);
        $this->assertSame(2, $plan['remote_scanned']);
    }

    public function test_deletes_listing_when_shop_product_is_no_longer_public(): void
    {
        $category = Category::factory()->create();
        $product = Product::query()->create([
            'external_product_id' => (string) Str::uuid(),
            'name' => 'Gone from shop',
            'slug' => 'gone-'.Str::random(6),
            'is_public' => false,
            'status' => 'inactive',
            'category_id' => $category->id,
            'olx_listing_id' => '303',
            'olx_managed' => false,
            'available_stock' => 0,
        ]);

        $client = Mockery::mock(OlxApiClient::class);
        $client->shouldReceive('listShopListingIds')->once()->andReturn([303]);

        $settings = Mockery::mock(OlxSyncSettings::class);
        $settings->shouldReceive('credentials')->andReturn(['username' => 'shop']);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('isEligible')->with(Mockery::on(fn ($item): bool => (int) $item->id === (int) $product->id))->andReturn(false);
        $scope->shouldReceive('resolveCategoryMapping')->andReturn(new OlxCategoryMapping(['olx_category_id' => 10]));

        $plan = (new OlxProfileReconciler($client, $settings, $scope))->plan();

        $this->assertSame([$product->id], $plan['delete_product_ids']);
        $this->assertSame([], $plan['delete_listing_ids']);
    }
}
