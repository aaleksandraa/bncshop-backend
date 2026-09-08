<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasCategoryProbe;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Ananas\AnanasCategoryProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnanasCategoryProbeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bnc.ananas_env' => 'stage',
            'bnc.ananas_client_id' => 'test-client-id',
            'bnc.ananas_client_secret' => 'test-client-secret',
            'bnc.ananas_allow_catalog_writes' => true,
            'bnc.ananas_vat_rate' => 0,
            'bnc.ananas_import_poll_interval_seconds' => 0,
            'bnc.ananas_import_poll_max_attempts' => 1,
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);
    }

    public function test_category_matches_is_case_insensitive(): void
    {
        $service = app(AnanasCategoryProbeService::class);

        $this->assertTrue($service->categoryMatches('Laptopi', ['Laptopi', 'IT oprema']));
        $this->assertTrue($service->categoryMatches('laptopi', ['Laptopi']));
        $this->assertFalse($service->categoryMatches('Laptop', ['Laptopi']));
    }

    public function test_probe_dry_run_does_not_call_import(): void
    {
        Http::fake();

        [$product, $mapping] = $this->createProbeFixtures('Laptopi');

        $result = app(AnanasCategoryProbeService::class)->probe(
            mapping: $mapping,
            product: $product,
            dryRun: true,
        );

        $this->assertSame(0, $result->probeId);
        $this->assertSame(AnanasCategoryProbe::STATUS_SUBMITTED, $result->status);
        Http::assertNothingSent();
    }

    public function test_probe_validates_category_from_get_products_response(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/import' => Http::response([
                'id' => '11111111-1111-1111-1111-111111111111',
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/products*' => Http::response([
                [
                    'id' => 9001,
                    'ean' => '1234567890123',
                    'productType' => 'ITShop',
                    'categories' => ['Laptopi', 'Računari'],
                    'status' => 'READY_FOR_PUBLISH',
                ],
            ], 200),
        ]);

        [$product, $mapping] = $this->createProbeFixtures('Laptopi');

        $result = app(AnanasCategoryProbeService::class)->probe(
            mapping: $mapping,
            product: $product,
            waitSeconds: 0,
        );

        $this->assertTrue($result->isValidated());
        $this->assertSame(['Laptopi', 'Računari'], $result->observedCategories);
        $this->assertSame('9001', $result->remoteProductId);

        $mapping->refresh();
        $this->assertSame(AnanasCategoryMapping::VALIDATION_VALIDATED, $mapping->category_validation_status);
        $this->assertSame(['Laptopi', 'Računari'], $mapping->observed_categories);

        $this->assertDatabaseHas('ananas_category_probes', [
            'category_mapping_id' => $mapping->id,
            'status' => AnanasCategoryProbe::STATUS_VALIDATED,
            'category_candidate' => 'Laptopi',
        ]);
    }

    public function test_probe_fails_when_observed_categories_do_not_match_candidate(): void
    {
        Http::fake([
            'api.qa2.ananastest.com/iam/api/v1/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 900,
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/import' => Http::response([
                'id' => '22222222-2222-2222-2222-222222222222',
            ], 200),
            'api.qa2.ananastest.com/product/api/v1/merchant-integration/products*' => Http::response([
                [
                    'id' => 9002,
                    'ean' => '1234567890123',
                    'productType' => 'ITShop',
                    'categories' => ['Desktop računari'],
                    'status' => 'READY_FOR_PUBLISH',
                ],
            ], 200),
        ]);

        [$product, $mapping] = $this->createProbeFixtures('Laptopi');

        $result = app(AnanasCategoryProbeService::class)->probe(
            mapping: $mapping,
            product: $product,
            waitSeconds: 0,
        );

        $this->assertSame(AnanasCategoryProbe::STATUS_FAILED, $result->status);
        $mapping->refresh();
        $this->assertSame(AnanasCategoryMapping::VALIDATION_FAILED, $mapping->category_validation_status);
    }

    /**
     * @return array{0: Product, 1: AnanasCategoryMapping}
     */
    private function createProbeFixtures(string $categoryCandidate): array
    {
        $category = Category::factory()->create();

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => $categoryCandidate,
            'is_enabled' => false,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'barcode' => '1234567890123',
            'available_stock' => 2,
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.test/product.jpg',
            'public_url' => 'https://cdn.example.test/product.jpg',
            'status' => 'active',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Težina',
            'display_name' => 'Težina',
            'internal_type' => 'text',
            'is_public' => true,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Težina',
            'raw_value' => '2 kg',
            'normalized_value' => '2',
            'normalized_type' => 'number',
        ]);

        return [
            $product->fresh(['images', 'attributeValues.attributeDefinition', 'manufacturer']),
            $mapping,
        ];
    }
}
