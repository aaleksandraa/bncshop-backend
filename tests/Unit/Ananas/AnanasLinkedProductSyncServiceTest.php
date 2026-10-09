<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnanasLinkedProductSyncServiceTest extends TestCase
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
            'bnc.ananas_vat_rate' => 17,
            'bnc.ananas_stage_token_url' => 'https://api.qa2.ananastest.com/iam/api/v1/auth/token',
            'bnc.ananas_stage_product_base_url' => 'https://api.qa2.ananastest.com',
        ]);
    }

    public function test_zero_stock_dry_run_uses_bnc_stock_for_merchant_rows_without_qty(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/auth/token')) {
                return Http::response([
                    'access_token' => 'token-abc',
                    'expires_in' => 900,
                ], 200);
            }

            if (str_contains($request->url(), '/merchant-integration/products')) {
                return Http::response([
                    'content' => [
                        [
                            'id' => 2567071,
                            'ean' => '4711387783597',
                            'stockLevel' => 0,
                            'basePrice' => 2519,
                        ],
                        [
                            'id' => 2567072,
                            'ean' => '4711387783627',
                            'stockLevel' => 12,
                            'basePrice' => 3705,
                        ],
                    ],
                    'totalElements' => 2,
                ], 200);
            }

            return Http::response(['unexpected' => $request->url()], 500);
        });

        $zero = $this->createProductWithWeight('4711387783597', 8, 2519);
        $this->createProductWithWeight('4711387783627', 4, 3705);

        $result = app(AnanasLinkedProductSyncService::class)->syncLinkedStockAndPrice(
            limit: 25,
            dryRun: true,
            vatRate: 17,
            zeroStock: true,
        );

        $this->assertSame(1, $result['updated']);
        $this->assertSame(2567071, (int) $result['items'][0]['id']);
        $this->assertSame($zero->id, (int) $result['items'][0]['product_id']);
        $this->assertSame(8, (int) $result['items'][0]['stockLevel']);
        $this->assertSame(17, (int) $result['items'][0]['vat']);
        $this->assertSame(
            AnanasProductMapping::LOCAL_DISABLED,
            AnanasProductMapping::query()->where('product_id', $zero->id)->value('local_status'),
        );
    }

    private function createProductWithWeight(string $ean, int $stock, float $price): Product
    {
        $product = Product::factory()->create([
            'barcode' => $ean,
            'regular_price' => $price,
            'display_price' => $price,
            'available_stock' => $stock,
            'is_public' => true,
            'status' => 'active',
            'is_refurbished' => false,
            'is_set' => false,
        ]);

        $definition = AttributeDefinition::query()->firstOrCreate(
            ['name' => 'Težina'],
            [
                'external_attribute_id' => (string) Str::uuid(),
                'display_name' => 'Težina',
                'internal_type' => 'text',
                'is_public' => true,
            ],
        );

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $definition->id,
            'attribute_name_snapshot' => 'Težina',
            'raw_value' => '1.2 kg',
            'normalized_value' => '1.2',
            'normalized_type' => 'number',
        ]);

        return $product;
    }
}
