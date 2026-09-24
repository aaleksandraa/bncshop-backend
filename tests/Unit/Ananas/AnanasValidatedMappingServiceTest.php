<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Services\Ananas\AnanasExportScope;
use App\Services\Ananas\AnanasValidatedMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasValidatedMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Category $laptops;

    private Category $tvMounts;

    private Category $emptyNode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = Category::factory()->create(['name' => 'Laptopi', 'display_name' => 'Laptopi']);
        $this->tvMounts = Category::factory()->create(['name' => 'TV nosači', 'display_name' => 'TV nosači']);
        $this->emptyNode = Category::factory()->create(['name' => 'Prazni Laptopi čvor']);

        config(['bnc.ananas_validated_mappings' => [
            [
                'category_id' => $this->laptops->id,
                'ananas_product_type' => 'ITShop',
                'ananas_category' => 'Gaming laptopi',
                'include_descendants' => true,
                'is_enabled' => true,
                'observed_categories' => ['Gaming laptopi'],
                'notes' => 'Stage GET 2566378',
            ],
            [
                'category_id' => $this->tvMounts->id,
                'ananas_product_type' => 'ITShop',
                'ananas_category' => 'Nosači za televizor',
                'include_descendants' => true,
                'is_enabled' => true,
                'observed_categories' => ['Nosači za televizor'],
                'notes' => 'Stage GET 2566379',
            ],
        ]]);
    }

    public function test_apply_creates_enabled_validated_mappings(): void
    {
        $result = app(AnanasValidatedMappingService::class)->apply();

        $this->assertCount(2, $result['applied']);
        $this->assertSame([], $result['skipped']);
        $this->assertTrue(AnanasProductType::query()->where('name', 'ITShop')->exists());

        $laptopMapping = AnanasCategoryMapping::query()->where('category_id', $this->laptops->id)->first();
        $this->assertNotNull($laptopMapping);
        $this->assertSame('Gaming laptopi', $laptopMapping->ananas_category);
        $this->assertTrue($laptopMapping->is_enabled);
        $this->assertSame(AnanasCategoryMapping::VALIDATION_VALIDATED, $laptopMapping->category_validation_status);
        $this->assertSame(['Gaming laptopi'], $laptopMapping->observed_categories);

        $mountMapping = AnanasCategoryMapping::query()->where('category_id', $this->tvMounts->id)->first();
        $this->assertNotNull($mountMapping);
        $this->assertSame('Nosači za televizor', $mountMapping->ananas_category);
        $this->assertTrue($mountMapping->is_enabled);
    }

    public function test_apply_updates_existing_mapping_and_disables_stale_laptopi_candidate(): void
    {
        AnanasCategoryMapping::query()->create([
            'category_id' => $this->laptops->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Laptopi',
            'is_enabled' => false,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_FAILED,
        ]);

        AnanasCategoryMapping::query()->create([
            'category_id' => $this->emptyNode->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_UNKNOWN,
        ]);

        $result = app(AnanasValidatedMappingService::class)->apply();

        $this->assertSame('updated', $result['applied'][0]['action']);
        $this->assertCount(1, $result['disabled']);

        $laptopMapping = AnanasCategoryMapping::query()->where('category_id', $this->laptops->id)->first();
        $this->assertSame('Gaming laptopi', $laptopMapping?->ananas_category);
        $this->assertTrue((bool) $laptopMapping?->is_enabled);

        $stale = AnanasCategoryMapping::query()->where('category_id', $this->emptyNode->id)->first();
        $this->assertFalse((bool) $stale?->is_enabled);
        $this->assertSame(AnanasCategoryMapping::VALIDATION_FAILED, $stale?->category_validation_status);
    }

    public function test_apply_skips_missing_bnc_category(): void
    {
        config(['bnc.ananas_validated_mappings' => [
            [
                'category_id' => 999999,
                'ananas_product_type' => 'ITShop',
                'ananas_category' => 'Gaming laptopi',
                'include_descendants' => true,
                'is_enabled' => true,
                'observed_categories' => ['Gaming laptopi'],
                'notes' => '',
            ],
        ]]);

        $result = app(AnanasValidatedMappingService::class)->apply();

        $this->assertSame([], $result['applied']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertStringContainsString('999999', $result['skipped'][0]);
    }

    public function test_apply_flushes_export_scope_cache(): void
    {
        $scope = app(AnanasExportScope::class);
        $this->assertCount(0, $scope->enabledMappings());

        (new AnanasValidatedMappingService($scope))->apply();

        $this->assertCount(2, $scope->enabledMappings());
        $this->assertContains($this->laptops->id, $scope->scopedCategoryIds());
        $this->assertContains($this->tvMounts->id, $scope->scopedCategoryIds());
    }

    public function test_apply_command_upserts_mappings(): void
    {
        $this->artisan('bnc:ananas-apply-validated-mappings')
            ->assertSuccessful();

        $this->assertSame(2, AnanasCategoryMapping::query()->where('is_enabled', true)->count());
    }

    public function test_create_command_requires_update_flag_for_existing_mapping(): void
    {
        $this->artisan('bnc:ananas-create-category-mapping', [
            '--category-id' => $this->laptops->id,
            '--product-type' => 'ITShop',
            '--ananas-category' => 'Laptopi',
        ])->assertSuccessful();

        $this->artisan('bnc:ananas-create-category-mapping', [
            '--category-id' => $this->laptops->id,
            '--product-type' => 'ITShop',
            '--ananas-category' => 'Gaming laptopi',
            '--enable' => true,
        ])->assertFailed();

        $this->artisan('bnc:ananas-create-category-mapping', [
            '--category-id' => $this->laptops->id,
            '--product-type' => 'ITShop',
            '--ananas-category' => 'Gaming laptopi',
            '--enable' => true,
            '--update' => true,
        ])->assertSuccessful();

        $mapping = AnanasCategoryMapping::query()->where('category_id', $this->laptops->id)->first();
        $this->assertSame('Gaming laptopi', $mapping?->ananas_category);
        $this->assertTrue((bool) $mapping?->is_enabled);
    }
}
