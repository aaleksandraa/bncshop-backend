<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Services\Catalog\ProductReadCache;
use App\Services\Homepage\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomepageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_weekly_offer_flushes_tagged_cache(): void
    {
        if (! app(ProductReadCache::class)->supportsTags()) {
            $this->markTestSkipped('Cache tags are not supported in the current store.');
        }

        $product = Product::factory()->create();
        $settings = app(HomepageSettings::class);

        Cache::tags(['homepage:weekly-offer'])->put('homepage:weekly-offer:stale', ['stale' => true], 120);

        $settings->saveWeeklyOffer([
            'enabled' => true,
            'title' => 'Test ponuda',
            'subtitle' => null,
            'layout' => 'spotlight_card',
            'product_limit' => 1,
            'product_ids' => [$product->id],
        ]);

        $this->assertNull(Cache::tags(['homepage:weekly-offer'])->get('homepage:weekly-offer:stale'));

        $stored = SystemSetting::query()
            ->where('key', 'homepage_weekly_offer')
            ->value('value');

        $this->assertSame([$product->id], $stored['product_ids'] ?? null);
        $this->assertSame('Test ponuda', $stored['title'] ?? null);
    }

    public function test_save_category_chips_persists_settings(): void
    {
        $settings = app(HomepageSettings::class);

        $settings->saveCategoryChips([
            'enabled' => true,
            'title' => 'Odaberite kategoriju',
            'subtitle' => 'Brzo do proizvoda',
            'category_limit' => 4,
            'category_ids' => [10, 20, 30, 40],
        ]);

        $stored = SystemSetting::query()
            ->where('key', 'homepage_category_chips')
            ->value('value');

        $this->assertSame([10, 20, 30, 40], $stored['category_ids'] ?? null);
        $this->assertSame('Odaberite kategoriju', $stored['title'] ?? null);
        $this->assertSame(4, $stored['category_limit'] ?? null);
    }

    public function test_resolved_category_chip_ids_fall_back_to_defaults_when_empty(): void
    {
        $racunari = Category::factory()->create([
            'name' => 'Racunari',
            'full_slug' => 'it-oprema/racunari',
            'depth' => 1,
            'status' => 'active',
        ]);
        $laptopi = Category::factory()->create([
            'name' => 'Laptopi',
            'full_slug' => 'it-oprema/laptopi',
            'depth' => 1,
            'status' => 'active',
        ]);

        $settings = app(HomepageSettings::class);

        $ids = $settings->resolvedCategoryChipIds([
            'category_ids' => [],
        ]);

        $this->assertContains($racunari->id, $ids);
        $this->assertContains($laptopi->id, $ids);
    }

    public function test_save_featured_products_persists_settings(): void
    {
        $product = Product::factory()->create();
        $settings = app(HomepageSettings::class);

        $settings->saveFeaturedProducts([
            'tiles_enabled' => true,
            'rows_enabled' => false,
            'tiles_eyebrow' => 'Top',
            'tiles_title' => 'Izbor kupaca',
            'rows_eyebrow' => 'Detaljno',
            'rows_title' => 'Lista',
            'tiles_limit' => 4,
            'rows_limit' => 2,
            'product_ids' => [$product->id],
        ]);

        $stored = SystemSetting::query()
            ->where('key', 'homepage_featured_products')
            ->value('value');

        $this->assertSame([$product->id], $stored['product_ids'] ?? null);
        $this->assertTrue($stored['tiles_enabled'] ?? false);
        $this->assertFalse($stored['rows_enabled'] ?? true);
    }
}
