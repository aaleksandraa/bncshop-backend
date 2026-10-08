<?php

namespace Tests\Feature;

use App\Models\PartnerApiClient;
use App\Models\Product;
use App\Services\Integrations\PartnerExportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PartnerUsedProductExportTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = '';

    private PartnerApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerExportSettings::class)->save([
            'enabled' => true,
            'used_enabled' => true,
            'require_https' => false,
            'require_ip_allowlist' => false,
        ]);

        $this->client = PartnerApiClient::query()->create([
            'name' => 'Used partner',
            'code' => 'used-partner',
            'type' => PartnerApiClient::TYPE_BASIC,
            'catalog_scope' => PartnerApiClient::CATALOG_USED,
            'enabled' => true,
            'require_ip_allowlist' => false,
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
        ]);

        $this->apiKey = $this->client->rotateApiKey();
    }

    public function test_returns_forbidden_when_used_export_is_disabled(): void
    {
        app(PartnerExportSettings::class)->save(['used_enabled' => false]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/used-products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Partner export API za polovne proizvode je isključen.');
    }

    public function test_new_catalog_key_is_rejected_on_used_endpoint(): void
    {
        $newClient = PartnerApiClient::query()->create([
            'name' => 'New only',
            'code' => 'new-only',
            'type' => PartnerApiClient::TYPE_BASIC,
            'catalog_scope' => PartnerApiClient::CATALOG_NEW,
            'enabled' => true,
        ]);
        $newKey = $newClient->rotateApiKey();

        $this->withHeader('X-API-Key', $newKey)
            ->getJson('/api/v1/partner/used-products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Ovaj API ključ nije ovlašten za polovni katalog.');
    }

    public function test_used_catalog_key_is_rejected_on_new_products_endpoint(): void
    {
        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Ovaj API ključ nije ovlašten za katalog novih proizvoda.');
    }

    public function test_exports_only_public_active_eline_refurbished_including_zero_stock(): void
    {
        $included = Product::factory()->create([
            'name' => 'Polovni laptop',
            'sku' => 'EL-100',
            'eline_sifra' => 'EL-100',
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
            'available_stock' => 0,
            'regular_price' => 499,
            'display_price' => 499,
            'on_sale' => false,
        ]);

        Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
            'available_stock' => 3,
        ]);

        Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => false,
            'is_public' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'import_source' => 'a1',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => false,
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/integrations/used-partner/used-products');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $zeroStock = collect($response->json('data'))->firstWhere('id', $included->id);

        $this->assertNotNull($zeroStock);
        $this->assertSame(0, $zeroStock['zaliha']);
        $this->assertSame('nema', $zeroStock['dostupnost']);
        $this->assertSame('eline', $zeroStock['izvor']);
        $this->assertSame('polovan', $zeroStock['stanje_artikla']);
    }

    public function test_full_export_includes_extended_fields(): void
    {
        $this->client->update(['type' => PartnerApiClient::TYPE_FULL]);

        Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
            'available_stock' => 2,
            'description' => 'Opis polovnog',
        ]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/used-products')
            ->assertOk()
            ->assertJsonPath('data.0.opis', 'Opis polovnog')
            ->assertJsonPath('data.0.izvor', 'eline')
            ->assertJsonPath('data.0.dostupnost', 'u_radnji');
    }

    public function test_removals_requires_modified_after(): void
    {
        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/used-products/removals')
            ->assertStatus(422)
            ->assertJsonPath('errors.0', 'ModifiedAfter (ili updated_since) je obavezan za feed uklanjanja.');
    }

    public function test_removals_lists_unpublished_eline_refurbished_since_date(): void
    {
        Carbon::setTestNow('2026-08-01 12:00:00');

        $removed = Product::factory()->create([
            'sku' => 'EL-200',
            'eline_sifra' => 'EL-200',
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => false,
            'status' => 'active',
        ]);
        $removed->forceFill(['updated_at' => Carbon::parse('2026-07-20 10:00:00')])->saveQuietly();

        Product::factory()->create([
            'import_source' => 'eline',
            'is_refurbished' => true,
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/used-products/removals?ModifiedAfter=2026-07-01T00:00:00Z')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $removed->id)
            ->assertJsonPath('data.0.sifra', 'EL-200')
            ->assertJsonPath('data.0.razlog', 'nije_javan');

        Carbon::setTestNow();
    }
}
