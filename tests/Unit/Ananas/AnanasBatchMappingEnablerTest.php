<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Services\Ananas\AnanasBatchMappingEnabler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasBatchMappingEnablerTest extends TestCase
{
    use RefreshDatabase;

    public function test_enables_one_to_one_leaves_and_skips_laptops(): void
    {
        $monitors = Category::factory()->create(['name' => 'Monitori', 'display_name' => 'Monitori']);
        $laptops = Category::factory()->create(['name' => 'Laptopi', 'display_name' => 'Laptopi']);
        $gaming = Category::factory()->create(['name' => 'Laptopi gaming']);
        $fax = Category::factory()->create(['name' => 'Fax', 'display_name' => 'Fax']);

        $monitorMapping = AnanasCategoryMapping::query()->create([
            'category_id' => $monitors->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Monitori',
            'is_enabled' => false,
            'include_descendants' => true,
        ]);
        AnanasCategoryMapping::query()->create([
            'category_id' => $laptops->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Laptopi',
            'is_enabled' => false,
            'include_descendants' => true,
        ]);
        $gamingMapping = AnanasCategoryMapping::query()->create([
            'category_id' => $gaming->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);
        AnanasCategoryMapping::query()->create([
            'category_id' => $fax->id,
            'ananas_product_type' => 'KnjižaraOfficeSchool',
            'ananas_category' => 'Fax aparati',
            'is_enabled' => false,
            'include_descendants' => true,
        ]);

        $result = app(AnanasBatchMappingEnabler::class)->enable();

        $this->assertTrue($monitorMapping->fresh()?->is_enabled);
        $this->assertFalse(AnanasCategoryMapping::query()->where('ananas_category', 'Laptopi')->first()?->is_enabled);
        $this->assertFalse(AnanasCategoryMapping::query()->where('ananas_category', 'Fax aparati')->first()?->is_enabled);
        $this->assertTrue($gamingMapping->fresh()?->is_enabled);

        $names = array_column($result['enabled'], 'ananas_category');
        $this->assertContains('Monitori', $names);
        $this->assertContains('Gaming laptopi', $names);
        $this->assertNotContains('Laptopi', $names);
    }

    public function test_dry_run_does_not_write(): void
    {
        $toners = Category::factory()->create(['name' => 'Toneri', 'display_name' => 'Toneri']);
        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $toners->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Toneri',
            'is_enabled' => false,
            'include_descendants' => true,
        ]);

        app(AnanasBatchMappingEnabler::class)->enable(dryRun: true);

        $this->assertFalse($mapping->fresh()?->is_enabled);
    }
}
