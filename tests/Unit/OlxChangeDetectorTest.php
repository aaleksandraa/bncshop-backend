<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\OlxCategoryMapping;
use App\Models\Product;
use App\Services\Olx\OlxAttributeResolver;
use App\Services\Olx\OlxChangeDetector;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxListingMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OlxChangeDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_returns_empty_buckets_when_no_category_mappings(): void
    {
        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([]);

        $mapper = Mockery::mock(OlxListingMapper::class);

        $detector = new OlxChangeDetector($scope, $mapper, $this->resolverAllowingCreates());
        $result = $detector->detect();

        $this->assertSame(0, $result['scanned']);
        $this->assertSame([], $result['create']);
        $this->assertSame([], $result['update']);
        $this->assertSame([], $result['hide']);
        $this->assertSame([], $result['unhide']);
        $this->assertSame([], $result['delete']);
        $this->assertSame(0, $result['frozen_invalid_create']);
    }

    public function test_freezes_unchanged_invalid_creates(): void
    {
        $category = Category::factory()->create();
        $product = $this->makeProduct([
            'category_id' => $category->id,
            'olx_listing_status' => 'error',
            'olx_export_hash' => 'same-hash',
            'olx_last_error' => 'Nedostaju obavezni OLX atributi: RAM (#246)',
        ]);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([$category->id]);
        $scope->shouldReceive('isEligible')->andReturn(true);
        $scope->shouldReceive('resolveCategoryMapping')->andReturn(new OlxCategoryMapping(['olx_category_id' => 10]));
        $scope->shouldReceive('isLegacyProtected')->andReturn(false);

        $mapper = Mockery::mock(OlxListingMapper::class);
        $mapper->shouldReceive('map')->andReturn(['title' => 'x']);
        $mapper->shouldReceive('fingerprintPayload')->andReturn('same-hash');

        $detector = new OlxChangeDetector($scope, $mapper, $this->resolverAllowingCreates());
        $result = $detector->detect();

        $this->assertSame([], $result['create']);
        $this->assertSame(1, $result['frozen_invalid_create']);
    }

    public function test_collects_ineligible_listed_products_for_delete(): void
    {
        $product = $this->makeProduct([
            'is_public' => false,
            'status' => 'inactive',
            'olx_listing_id' => '12345',
            'olx_managed' => true,
            'olx_listing_status' => 'active',
        ]);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([]);
        $scope->shouldReceive('isLegacyProtected')->andReturn(false);
        $scope->shouldReceive('isEligible')->andReturn(false);

        $mapper = Mockery::mock(OlxListingMapper::class);

        $detector = new OlxChangeDetector($scope, $mapper, $this->resolverAllowingCreates());
        $result = $detector->detect();

        $this->assertSame([$product->id], $result['delete']);
    }

    public function test_detect_stock_hides_zero_stock_and_skips_creates(): void
    {
        $hide = $this->makeProduct([
            'available_stock' => 0,
            'olx_listing_id' => '111',
            'olx_managed' => true,
            'olx_listing_status' => 'active',
        ]);
        $this->makeProduct([
            'available_stock' => 4,
            'olx_listing_id' => null,
            'olx_managed' => false,
        ]);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('isLegacyProtected')->andReturn(false);
        $scope->shouldReceive('isEligible')->andReturn(true);
        $scope->shouldReceive('resolveCategoryMapping')->andReturn(new OlxCategoryMapping(['olx_category_id' => 10]));

        $mapper = Mockery::mock(OlxListingMapper::class);

        $detector = new OlxChangeDetector($scope, $mapper, $this->resolverAllowingCreates());
        $result = $detector->detectStock();

        $this->assertSame([], $result['create']);
        $this->assertSame([], $result['update']);
        $this->assertSame([$hide->id], $result['hide']);
    }

    public function test_does_not_queue_create_when_required_attributes_are_missing(): void
    {
        $category = Category::factory()->create();
        $blocked = $this->makeProduct(['category_id' => $category->id]);
        $ready = $this->makeProduct(['category_id' => $category->id]);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([$category->id]);
        $scope->shouldReceive('isEligible')->andReturn(true);
        $scope->shouldReceive('resolveCategoryMapping')->andReturn(new OlxCategoryMapping(['olx_category_id' => 10]));
        $scope->shouldReceive('isLegacyProtected')->andReturn(false);

        $mapper = Mockery::mock(OlxListingMapper::class);
        $mapper->shouldReceive('map')->andReturn(['title' => 'x']);
        $mapper->shouldReceive('fingerprintPayload')->andReturn('hash-1');

        $resolver = Mockery::mock(OlxAttributeResolver::class);
        $resolver->shouldReceive('missingRequiredForPublish')
            ->with(Mockery::on(fn ($product): bool => (int) $product->id === (int) $blocked->id), 10)
            ->andReturn([246 => 'RAM']);
        $resolver->shouldReceive('missingRequiredForPublish')
            ->with(Mockery::on(fn ($product): bool => (int) $product->id === (int) $ready->id), 10)
            ->andReturn([]);

        $detector = new OlxChangeDetector($scope, $mapper, $resolver);
        $result = $detector->detect();

        $this->assertSame([$ready->id], $result['create']);
        $this->assertSame(1, $result['frozen_invalid_create']);
        $this->assertSame('error', $blocked->fresh()->olx_listing_status);
        $this->assertNull($ready->fresh()->olx_listing_id);
    }

    public function test_queues_newer_valid_creates_first(): void
    {
        $category = Category::factory()->create();
        $older = $this->makeProduct(['category_id' => $category->id]);
        $newer = $this->makeProduct(['category_id' => $category->id]);

        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([$category->id]);
        $scope->shouldReceive('isEligible')->andReturn(true);
        $scope->shouldReceive('resolveCategoryMapping')->andReturn(new OlxCategoryMapping(['olx_category_id' => 10]));
        $scope->shouldReceive('isLegacyProtected')->andReturn(false);

        $mapper = Mockery::mock(OlxListingMapper::class);
        $mapper->shouldReceive('map')->andReturn(['title' => 'x']);
        $mapper->shouldReceive('fingerprintPayload')->andReturn('hash-1');

        $detector = new OlxChangeDetector($scope, $mapper, $this->resolverAllowingCreates());
        $result = $detector->detect();

        $this->assertSame([$newer->id, $older->id], $result['create']);
    }

    private function resolverAllowingCreates(): OlxAttributeResolver
    {
        $resolver = Mockery::mock(OlxAttributeResolver::class);
        $resolver->shouldReceive('missingRequiredForPublish')->andReturn([]);

        return $resolver;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'external_product_id' => (string) Str::uuid(),
            'name' => 'OLX product '.Str::random(6),
            'slug' => 'olx-product-'.Str::random(8),
            'is_public' => true,
            'status' => 'active',
            'api_stock' => 2,
            'available_stock' => 2,
            'stock_status' => 'in_stock',
        ], $overrides));
    }
}
