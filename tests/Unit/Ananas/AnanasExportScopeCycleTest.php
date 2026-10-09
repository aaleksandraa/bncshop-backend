<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Services\Ananas\AnanasExportScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasExportScopeCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_descendant_walk_stops_when_categories_form_a_cycle(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $parent->update(['parent_id' => $child->id]);

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $parent->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);

        $count = app(AnanasExportScope::class)->scopedProductCountForMapping($mapping);

        $this->assertSame(0, $count);
    }
}
