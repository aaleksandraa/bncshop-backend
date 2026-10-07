<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ananas\AnanasEligibilityReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasEligibilityReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_report_ignores_disabled_mapping_products(): void
    {
        $enabledCat = Category::factory()->create();
        $disabledCat = Category::factory()->create();

        AnanasCategoryMapping::query()->create([
            'category_id' => $enabledCat->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Monitori',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);
        AnanasCategoryMapping::query()->create([
            'category_id' => $disabledCat->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'HDD',
            'is_enabled' => false,
            'include_descendants' => true,
        ]);

        Product::factory()->create([
            'category_id' => $enabledCat->id,
            'barcode' => '',
            'regular_price' => 10,
        ]);
        Product::factory()->create([
            'category_id' => $disabledCat->id,
            'barcode' => '',
            'regular_price' => 10,
        ]);

        $enabledOnly = app(AnanasEligibilityReporter::class)->summarize(8, false);
        $allMapped = app(AnanasEligibilityReporter::class)->summarize(8, true);

        $this->assertSame('enabled', $enabledOnly['scope']);
        $this->assertSame(1, $enabledOnly['total_scanned']);
        $this->assertSame('all_mapped', $allMapped['scope']);
        $this->assertSame(2, $allMapped['total_scanned']);
        $this->assertCount(2, $allMapped['mappings']);
        $this->assertSame(0, $allMapped['eligible']);
    }
}
