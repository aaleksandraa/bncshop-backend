<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ananas\AnanasCategoryMappingProposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasCategoryMappingProposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_normalized_name_matches_and_skips_already_scoped_tree(): void
    {
        $laptops = Category::factory()->create(['name' => 'Laptopi', 'display_name' => 'Laptopi']);
        $monitors = Category::factory()->create(['name' => 'Monitori', 'display_name' => 'Monitori']);
        $phones = Category::factory()->create(['name' => 'Telefoni', 'display_name' => 'SMART mobilni telefoni']);

        AnanasCategoryMapping::query()->create([
            'category_id' => $laptops->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_VALIDATED,
        ]);

        AnanasProductType::query()->create(['name' => 'ITShop']);
        AnanasProductType::query()->create(['name' => 'Monitori']);
        AnanasProductType::query()->create(['name' => 'SMART mobilni telefoni']);

        Product::factory()->create(['category_id' => $monitors->id, 'is_public' => true, 'status' => 'active']);
        Product::factory()->create(['category_id' => $phones->id, 'is_public' => true, 'status' => 'active']);
        Product::factory()->create(['category_id' => $laptops->id, 'is_public' => true, 'status' => 'active']);

        $result = app(AnanasCategoryMappingProposer::class)->propose(minProducts: 1, minScore: 82);

        $ids = array_column($result['suggestions'], 'category_id');
        $this->assertContains($monitors->id, $ids);
        $this->assertNotContains($laptops->id, $ids);

        $monitor = collect($result['suggestions'])->firstWhere('category_id', $monitors->id);
        $this->assertSame('Monitori', $monitor['ananas_category']);
        $this->assertSame(100, $monitor['score']);
    }

    public function test_apply_creates_disabled_mappings(): void
    {
        $monitors = Category::factory()->create(['name' => 'Monitori']);
        AnanasProductType::query()->create(['name' => 'Monitori']);
        Product::factory()->create(['category_id' => $monitors->id, 'is_public' => true, 'status' => 'active']);

        $proposer = app(AnanasCategoryMappingProposer::class);
        $proposed = $proposer->propose(minProducts: 1, minScore: 82);
        $applied = $proposer->applySuggestions($proposed['suggestions'], enableExact: false);

        $this->assertSame(1, $applied['created']);
        $mapping = AnanasCategoryMapping::query()->where('category_id', $monitors->id)->first();
        $this->assertNotNull($mapping);
        $this->assertFalse($mapping->is_enabled);
        $this->assertSame('Monitori', $mapping->ananas_category);
        $this->assertSame('ITShop', $mapping->ananas_product_type);
    }
}
