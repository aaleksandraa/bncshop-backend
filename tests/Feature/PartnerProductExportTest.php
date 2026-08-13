<?php

namespace Tests\Feature;

use App\Models\PartnerApiClient;
use App\Models\Product;
use App\Services\Integrations\PartnerExportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PartnerProductExportTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = '';

    private PartnerApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        app(PartnerExportSettings::class)->save([
            'enabled' => true,
            'require_https' => false,
            'require_ip_allowlist' => false,
        ]);

        $this->client = PartnerApiClient::query()->create([
            'name' => 'Test partner',
            'code' => 'test-partner',
            'type' => PartnerApiClient::TYPE_BASIC,
            'enabled' => true,
            'require_ip_allowlist' => false,
            'allowed_ips' => [],
            'rate_limit_per_minute' => 60,
        ]);

        $this->apiKey = $this->client->rotateApiKey();
    }

    public function test_returns_forbidden_when_export_is_disabled(): void
    {
        app(PartnerExportSettings::class)->save(['enabled' => false]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Partner export API je isključen.');
    }

    public function test_returns_unauthorized_without_api_key(): void
    {
        $this->getJson('/api/v1/partner/products')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0', 'Neispravan ili nedostaje API ključ.');
    }

    public function test_returns_unauthorized_with_invalid_api_key(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-key')
            ->getJson('/api/v1/partner/products')
            ->assertUnauthorized();
    }

    public function test_rejects_when_ip_allowlist_is_required_but_empty(): void
    {
        app(PartnerExportSettings::class)->save(['require_ip_allowlist' => true]);

        $this->client->update([
            'require_ip_allowlist' => true,
            'allowed_ips' => [],
        ]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertStatus(503)
            ->assertJsonPath('errors.0', 'Partner export API zahtijeva definisan IP allowlist prije aktivacije u produkciji.');
    }

    public function test_rejects_invalid_ip_allowlist_entries_on_save(): void
    {
        $this->assertSame(['not-an-ip', '999.999.1.1'], PartnerApiClient::invalidAllowedIps("203.0.113.1\nnot-an-ip\n999.999.1.1"));
        $this->assertSame([], PartnerApiClient::invalidAllowedIps("203.0.113.1\n203.0.113.0/24"));
    }

    public function test_rejects_api_key_in_query_string(): void
    {
        $this->getJson('/api/v1/partner/products?api_key='.$this->apiKey)
            ->assertUnauthorized()
            ->assertJsonPath('errors.0', 'Neispravan ili nedostaje API ključ.');
    }

    public function test_rejects_non_https_when_required(): void
    {
        app(PartnerExportSettings::class)->save(['require_https' => true]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Partner export API zahtijeva HTTPS konekciju.');
    }

    public function test_rejects_ip_not_on_allowlist(): void
    {
        $this->client->update([
            'require_ip_allowlist' => false,
            'allowed_ips' => ['203.0.113.10', '203.0.113.0/24'],
        ]);

        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertForbidden()
            ->assertJsonPath('errors.0', 'Pristup sa ove IP adrese nije dozvoljen.');
    }

    public function test_allows_ip_on_allowlist(): void
    {
        $this->client->update([
            'allowed_ips' => ['203.0.113.10', '203.0.113.0/24'],
        ]);

        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertOk();
    }

    public function test_blocks_after_too_many_failed_auth_attempts(): void
    {
        RateLimiter::clear('partner-export:failed-auth:127.0.0.1');

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/partner/products')->assertUnauthorized();
        }

        $this->getJson('/api/v1/partner/products')
            ->assertStatus(429)
            ->assertJsonPath('errors.0', 'Previše neuspjelih pokušaja autentifikacije. Pokušajte ponovo za minut.');
    }

    public function test_adds_no_store_response_headers(): void
    {
        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_exports_public_active_products_with_expected_fields(): void
    {
        $product = Product::factory()->create([
            'name' => 'Test proizvod',
            'sku' => 'SKU-001',
            'barcode' => '1234567890123',
            'regular_price' => 199.99,
            'display_price' => 149.99,
            'on_sale' => true,
            'available_stock' => 12,
            'is_public' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'is_public' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'is_public' => true,
            'status' => 'draft',
        ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.naziv', 'Test proizvod')
            ->assertJsonPath('data.0.sifra', 'SKU-001')
            ->assertJsonPath('data.0.ean', '1234567890123')
            ->assertJsonPath('data.0.cijena', 199.99)
            ->assertJsonPath('data.0.akcijska_cijena', 149.99)
            ->assertJsonPath('data.0.zaliha', 12)
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_null_sale_price_when_product_is_not_on_sale(): void
    {
        Product::factory()->create([
            'regular_price' => 250,
            'display_price' => 250,
            'on_sale' => false,
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertOk()
            ->assertJsonPath('data.0.akcijska_cijena', null);
    }

    public function test_filters_products_by_updated_since(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $older = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $older->forceFill(['updated_at' => Carbon::parse('2026-06-01 10:00:00')])->saveQuietly();

        $newer = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $newer->forceFill(['updated_at' => Carbon::parse('2026-07-01 09:00:00')])->saveQuietly();

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products?updated_since=2026-06-15T00:00:00%2B02:00')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('meta.filters.updated_since', '2026-06-15T00:00:00+02:00');

        Carbon::setTestNow();
    }

    public function test_validates_updated_since_format(): void
    {
        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products?updated_since=not-a-date')
            ->assertUnprocessable();
    }

    public function test_integration_route_filters_by_modified_after(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $older = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $older->forceFill(['updated_at' => Carbon::parse('2026-06-01 10:00:00')])->saveQuietly();

        $newer = Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);
        $newer->forceFill(['updated_at' => Carbon::parse('2026-07-01 09:00:00')])->saveQuietly();

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/integrations/test-partner/products?ModifiedAfter=2026-06-15T00:00:00Z&Page=1&PageSize=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('meta.filters.ModifiedAfter', '2026-06-15T00:00:00Z');

        Carbon::setTestNow();
    }

    public function test_integration_route_rejects_mismatched_partner_code(): void
    {
        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/integrations/other-partner/products')
            ->assertUnauthorized();
    }

    public function test_disabled_client_is_rejected(): void
    {
        $this->client->update(['enabled' => false]);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertUnauthorized();
    }

    public function test_blocks_after_daily_page_limit(): void
    {
        RateLimiter::clear('partner-export:daily-pages:'.$this->client->id);

        $this->client->update([
            'daily_page_limit' => 50,
            'rate_limit_per_minute' => 300,
        ]);

        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        RateLimiter::hit('partner-export:daily-pages:'.$this->client->id, 86400);
        for ($i = 1; $i < 50; $i++) {
            RateLimiter::hit('partner-export:daily-pages:'.$this->client->id, 86400);
        }

        $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products')
            ->assertStatus(429)
            ->assertJsonPath('errors.0', 'Dnevni limit preuzimanja je dostignut. Pokušajte ponovo za 24 sata ili kontaktirajte BNC.');
    }

    public function test_sanitizes_full_type_labels_and_keeps_fillable_fields(): void
    {
        $data = PartnerApiClient::sanitizeFormData([
            'name' => 'Shop',
            'code' => 'Full Shop',
            'type' => 'Puni (kategorija, opis, atributi, slike, brend)',
            'enabled' => true,
            'allowed_ips_text' => '203.0.113.10',
            'daily_page_limit' => 2000,
            'integration_url' => 'should-be-removed',
        ]);

        $this->assertSame('full-shop', $data['code']);
        $this->assertSame(PartnerApiClient::TYPE_FULL, $data['type']);
        $this->assertSame(['203.0.113.10'], $data['allowed_ips']);
        $this->assertArrayNotHasKey('integration_url', $data);
        $this->assertArrayNotHasKey('allowed_ips_text', $data);

        $client = PartnerApiClient::query()->create([
            'name' => 'Full shop',
            'code' => 'full-shop',
            'type' => PartnerApiClient::TYPE_FULL,
            'enabled' => true,
            'daily_page_limit' => 2000,
        ]);

        $this->assertTrue($client->isFullExport());
    }

    public function test_reuses_cached_product_count_across_pages(): void
    {
        Product::factory()->count(3)->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $first = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products?page=1&per_page=1')
            ->assertOk();

        Product::factory()->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $second = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/partner/products?page=2&per_page=1')
            ->assertOk();

        $this->assertSame(
            $first->json('meta.pagination.total'),
            $second->json('meta.pagination.total'),
        );
        $this->assertSame(3, $second->json('meta.pagination.total'));
    }
}
