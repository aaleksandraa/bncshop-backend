<?php

namespace Tests\Unit;

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
}
