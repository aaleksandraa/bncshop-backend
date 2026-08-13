<?php

namespace Tests\Feature;

use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\PartnerApiClient;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Services\Integrations\PartnerExportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProductFullExportTest extends TestCase
{
    use RefreshDatabase;

    private string $basicApiKey = '';

    private string $fullApiKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerExportSettings::class)->save([
            'enabled' => true,
            'require_https' => false,
            'require_ip_allowlist' => false,
        ]);

        $basicClient = PartnerApiClient::query()->create([
            'name' => 'Basic partner',
            'code' => 'basic-partner',
            'type' => PartnerApiClient::TYPE_BASIC,
            'enabled' => true,
            'require_ip_allowlist' => false,
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
        ]);
        $this->basicApiKey = $basicClient->rotateApiKey();

        $fullClient = PartnerApiClient::query()->create([
            'name' => 'Full partner',
            'code' => 'full-partner',
            'type' => PartnerApiClient::TYPE_FULL,
            'enabled' => true,
            'require_ip_allowlist' => false,
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
        ]);
        $this->fullApiKey = $fullClient->rotateApiKey();
    }

    public function test_basic_client_does_not_receive_full_fields(): void
    {
        $this->seedFullProduct();

        $response = $this->withHeader('X-API-Key', $this->basicApiKey)
            ->getJson('/api/integrations/basic-partner/products');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.opis')
            ->assertJsonMissingPath('data.0.kategorija')
            ->assertJsonMissingPath('data.0.atributi')
            ->assertJsonMissingPath('data.0.slike')
            ->assertJsonMissingPath('data.0.proizvodjac');
    }

    public function test_full_client_receives_extended_fields_without_internal_pricing(): void
    {
        $product = $this->seedFullProduct();

        $response = $this->withHeader('X-API-Key', $this->fullApiKey)
            ->getJson('/api/integrations/full-partner/products');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.naziv', 'Puni proizvod')
            ->assertJsonPath('data.0.sifra', 'FULL-001')
            ->assertJsonPath('data.0.ean', '9876543210123')
            ->assertJsonPath('data.0.cijena', 500)
            ->assertJsonPath('data.0.akcijska_cijena', 450)
            ->assertJsonPath('data.0.zaliha', 7)
            ->assertJsonPath('data.0.opis', '<p>Opis proizvoda</p>')
            ->assertJsonPath('data.0.kratki_opis', 'Kratki opis')
            ->assertJsonPath('data.0.kategorija.naziv', 'Laptopi')
            ->assertJsonPath('data.0.proizvodjac.naziv', 'Dell')
            ->assertJsonPath('data.0.atributi.0.naziv', 'Procesor')
            ->assertJsonPath('data.0.atributi.0.vrijednost', 'Intel Core i7')
            ->assertJsonCount(1, 'data.0.slike');

        $payload = json_encode($response->json('data.0'));

        $this->assertIsString($payload);
        $this->assertStringNotContainsString('margin', strtolower($payload));
        $this->assertStringNotContainsString('marza', strtolower($payload));
        $this->assertStringNotContainsString('nabavna', strtolower($payload));
        $this->assertStringNotContainsString('api_price', strtolower($payload));
        $this->assertStringNotContainsString('supplier_price', strtolower($payload));
    }

    private function seedFullProduct(): Product
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Dell']);
        $category = Category::factory()->create([
            'name' => 'Laptopi API',
            'display_name' => 'Laptopi',
            'full_slug' => 'racunari/laptopi',
        ]);

        $product = Product::factory()->create([
            'name' => 'Puni proizvod',
            'sku' => 'FULL-001',
            'barcode' => '9876543210123',
            'description' => '<p>Opis proizvoda</p>',
            'short_description' => 'Kratki opis',
            'regular_price' => 500,
            'display_price' => 450,
            'on_sale' => true,
            'available_stock' => 7,
            'margin_percentage' => 25,
            'api_price' => 300,
            'manufacturer_id' => $manufacturer->id,
            'category_id' => $category->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        $attribute = AttributeDefinition::query()->create([
            'external_attribute_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Procesor',
            'display_name' => 'Procesor',
            'api_type' => 0,
            'internal_type' => 'text',
            'is_public' => true,
            'is_filter' => false,
            'detail_sort_order' => 1,
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'attribute_definition_id' => $attribute->id,
            'attribute_name_snapshot' => 'Procesor',
            'raw_value' => 'Intel Core i7',
            'normalized_value' => 'Intel Core i7',
            'normalized_type' => 'text',
        ]);

        ProductImage::withoutEvents(function () use ($product): void {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_url' => 'https://cdn.example.test/product.jpg',
                'source_url' => 'https://cdn.example.test/product.jpg',
                'public_url' => 'https://cdn.example.test/product.jpg',
                'status' => 'active',
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        });

        return $product;
    }
}
