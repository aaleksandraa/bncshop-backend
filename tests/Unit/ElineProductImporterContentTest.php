<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\Product;
use App\Models\User;
use App\Services\Eline\ElineProductImporter;
use App\Services\Eline\ElineSupport;
use App\Services\Sync\FieldLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ElineProductImporterContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return Collection<string, ElineCategoryMapping>
     */
    private function mappingsFor(string $elineCategoryName): Collection
    {
        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => $elineCategoryName,
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'is_enabled' => true,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
        ]);

        return ElineCategoryMapping::query()
            ->with(['elineCategory', 'category'])
            ->where('is_enabled', true)
            ->get()
            ->keyBy(fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name);
    }

    private function createElineProduct(array $overrides = []): Product
    {
        $item = [
            'sifra' => 'CR-1',
            'naziv' => 'Stari naziv',
            'opis' => 'Stari opis',
            'eline_category' => 'Laptopi',
            'aktivan' => 255,
            'mpc' => 500.0,
            'stanje' => 3,
            'price_aktivan' => 255,
        ];

        return Product::query()->create(array_merge([
            'external_product_id' => ElineSupport::externalProductId('CR-1'),
            'import_source' => 'eline',
            'eline_sifra' => 'CR-1',
            'eline_feed_hash' => ElineSupport::feedHash($item),
            'sku' => 'CR-1',
            'name' => 'Stari naziv',
            'slug' => 'stari-naziv',
            'description' => 'Stari opis',
            'short_description' => 'Stari opis',
            'category_id' => Category::factory()->create()->id,
            'regular_price' => 500,
            'display_price' => 500,
            'api_price' => 500,
            'is_public' => true,
            'status' => 'active',
        ], $overrides));
    }

    public function test_apply_content_leaves_matching_products_unchanged(): void
    {
        $mappings = $this->mappingsFor('Laptopi');
        $product = $this->createElineProduct();

        $importer = app(ElineProductImporter::class);
        $result = $importer->applyContent([
            'sifra' => 'CR-1',
            'naziv' => 'Stari naziv',
            'opis' => 'Stari opis',
            'eline_category' => 'Laptopi',
        ], $mappings);

        $this->assertSame('unchanged', $result['status']);
        $this->assertSame('Stari naziv', $product->fresh()->name);
    }

    public function test_apply_content_updates_name_and_description_without_touching_price_or_slug(): void
    {
        $mappings = $this->mappingsFor('Laptopi');
        $product = $this->createElineProduct([
            'slug' => 'fixed-slug',
            'regular_price' => 500,
            'api_price' => 500,
        ]);

        $importer = app(ElineProductImporter::class);
        $result = $importer->applyContent([
            'sifra' => 'CR-1',
            'naziv' => 'Novi naziv',
            'opis' => 'Novi opis iz ERP-a',
            'eline_category' => 'Laptopi',
            'mpc' => 999.0,
            'stanje' => 0,
        ], $mappings);

        $this->assertSame('updated', $result['status']);

        $fresh = $product->fresh();
        $this->assertSame('Novi naziv', $fresh->name);
        $this->assertSame('Novi opis iz ERP-a', $fresh->description);
        $this->assertSame('fixed-slug', $fresh->slug);
        $this->assertSame(500.0, (float) $fresh->regular_price);
    }

    public function test_apply_content_updates_name_but_preserves_locked_description(): void
    {
        $mappings = $this->mappingsFor('Laptopi');
        $product = $this->createElineProduct([
            'description' => 'Ručno uređen opis',
            'short_description' => 'Ručno uređen opis',
        ]);

        $user = User::factory()->create();
        app(FieldLockService::class)->lockField($product, 'description', $user->id);
        app(FieldLockService::class)->lockField($product, 'short_description', $user->id);

        $importer = app(ElineProductImporter::class);
        $result = $importer->applyContent([
            'sifra' => 'CR-1',
            'naziv' => 'Novi naziv',
            'opis' => 'ERP opis koji se ne smije prepisati',
            'eline_category' => 'Laptopi',
        ], $mappings);

        $this->assertSame('updated', $result['status']);

        $fresh = $product->fresh();
        $this->assertSame('Novi naziv', $fresh->name);
        $this->assertSame('Ručno uređen opis', $fresh->description);
    }

    public function test_apply_content_skips_products_not_yet_in_catalog(): void
    {
        $mappings = $this->mappingsFor('Laptopi');
        $importer = app(ElineProductImporter::class);

        $result = $importer->applyContent([
            'sifra' => 'NEW-99',
            'naziv' => 'Potpuno novi',
            'opis' => 'Opis',
            'eline_category' => 'Laptopi',
        ], $mappings);

        $this->assertSame('skipped', $result['status']);
        $this->assertNull(Product::query()->where('eline_sifra', 'NEW-99')->first());
    }

    public function test_import_one_stores_feed_hash_with_local_description_when_locked(): void
    {
        $mappings = $this->mappingsFor('Laptopi');
        $product = $this->createElineProduct([
            'description' => 'Lokalni opis',
            'short_description' => 'Lokalni opis',
        ]);

        app(FieldLockService::class)->lockField($product, 'description');

        $source = ApiSource::query()->create([
            'name' => 'eLine',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $feedItem = [
            'sifra' => 'CR-1',
            'naziv' => 'Stari naziv',
            'opis' => 'ERP opis različit',
            'eline_category' => 'Laptopi',
            'aktivan' => 255,
            'mpc' => 500.0,
            'stanje' => 3,
            'price_aktivan' => 255,
        ];

        app(ElineProductImporter::class)->importOne($feedItem, $mappings, $source);

        $expectedHash = ElineSupport::feedHash(array_merge($feedItem, ['opis' => 'Lokalni opis']));

        $this->assertSame($expectedHash, $product->fresh()->eline_feed_hash);
        $this->assertSame('Lokalni opis', $product->fresh()->description);
    }
}
