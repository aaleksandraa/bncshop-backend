<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ananas\AnanasCategoryMappingProposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnanasCategoryMappingProposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_map_sporeti_to_sport_product_type(): void
    {
        $parent = Category::factory()->create(['name' => 'Bijela tehnika']);
        $sporeti = Category::factory()->create([
            'name' => 'Šporeti',
            'display_name' => 'Šporeti',
            'parent_id' => $parent->id,
        ]);
        Product::factory()->create(['category_id' => $sporeti->id, 'is_public' => true, 'status' => 'active']);

        $result = app(AnanasCategoryMappingProposer::class)->propose(minProducts: 1, minScore: 88);
        $row = collect($result['suggestions'])->firstWhere('category_id', $sporeti->id)
            ?? collect($result['unmatched'])->firstWhere('category_id', $sporeti->id);

        $this->assertNotNull($row);
        $this->assertNotSame('Sport', $row['ananas_category']);
    }

    public function test_monitors_match_itshop_leaf_and_skips_enabled_scope(): void
    {
        $it = Category::factory()->create(['name' => 'IT oprema']);
        $laptops = Category::factory()->create(['name' => 'Laptopi', 'parent_id' => $it->id]);
        $monitors = Category::factory()->create([
            'name' => 'Monitori',
            'display_name' => 'Monitori',
            'parent_id' => $it->id,
        ]);

        AnanasCategoryMapping::query()->create([
            'category_id' => $laptops->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_VALIDATED,
        ]);

        Product::factory()->create(['category_id' => $monitors->id, 'is_public' => true, 'status' => 'active']);
        Product::factory()->create(['category_id' => $laptops->id, 'is_public' => true, 'status' => 'active']);

        $result = app(AnanasCategoryMappingProposer::class)->propose(minProducts: 1, minScore: 88);
        $ids = array_column($result['suggestions'], 'category_id');

        $this->assertContains($monitors->id, $ids);
        $this->assertNotContains($laptops->id, $ids);

        $monitor = collect($result['suggestions'])->firstWhere('category_id', $monitors->id);
        $this->assertSame('Monitori', $monitor['ananas_category']);
        $this->assertSame('ITShop', $monitor['product_type']);
        $this->assertSame(100, $monitor['score']);
    }

    public function test_prune_removes_disabled_proposals_only(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create();

        $proposal = AnanasCategoryMapping::query()->create([
            'category_id' => $a->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Sport',
            'is_enabled' => false,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_UNKNOWN,
            'category_validation_notes' => 'Predloženo iz GET product-type (score 83).',
        ]);
        AnanasCategoryMapping::query()->create([
            'category_id' => $b->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_VALIDATED,
        ]);

        $pruned = app(AnanasCategoryMappingProposer::class)->pruneUnvalidatedProposals();

        $this->assertSame(1, $pruned['deleted']);
        $this->assertFalse(AnanasCategoryMapping::query()->whereKey($proposal->id)->exists());
        $this->assertTrue(AnanasCategoryMapping::query()->where('ananas_category', 'Gaming laptopi')->exists());
    }

    public function test_hdd_speakers_and_routers_match_itshop_leaves(): void
    {
        $it = Category::factory()->create(['name' => 'IT oprema']);
        $hdd = Category::factory()->create([
            'name' => 'HDD',
            'display_name' => 'HDD',
            'parent_id' => $it->id,
        ]);
        $speakers = Category::factory()->create([
            'name' => 'Zvučnici',
            'display_name' => 'Zvučnici',
            'parent_id' => $it->id,
        ]);
        $routers = Category::factory()->create([
            'name' => 'Ruteri',
            'display_name' => 'Ruteri',
            'parent_id' => $it->id,
        ]);

        foreach ([$hdd, $speakers, $routers] as $category) {
            Product::factory()->create(['category_id' => $category->id, 'is_public' => true, 'status' => 'active']);
        }

        $result = app(AnanasCategoryMappingProposer::class)->propose(minProducts: 1, minScore: 88);
        $byId = collect($result['suggestions'])->keyBy('category_id');

        $this->assertSame('HDD', $byId[$hdd->id]['ananas_category']);
        $this->assertSame('ITShop', $byId[$hdd->id]['product_type']);
        $this->assertSame(100, $byId[$hdd->id]['score']);
        $this->assertSame('Zvučnici', $byId[$speakers->id]['ananas_category']);
        $this->assertSame('Ruteri', $byId[$routers->id]['ananas_category']);
    }

    public function test_vacuums_match_aparati_not_kuca_i_vrt(): void
    {
        $parent = Category::factory()->create(['name' => 'Mali kućanski aparati']);
        $vacuums = Category::factory()->create([
            'name' => 'Usisivači',
            'display_name' => 'Usisivači',
            'parent_id' => $parent->id,
        ]);
        Product::factory()->create(['category_id' => $vacuums->id, 'is_public' => true, 'status' => 'active']);

        $result = app(AnanasCategoryMappingProposer::class)->propose(minProducts: 1, minScore: 88);
        $row = collect($result['suggestions'])->firstWhere('category_id', $vacuums->id);

        $this->assertNotNull($row);
        $this->assertSame('Usisivači', $row['ananas_category']);
        $this->assertSame('Aparati', $row['product_type']);
        $this->assertNotSame('Kuća i vrt', $row['ananas_category']);
        $this->assertNotSame('Sport', $row['ananas_category']);
    }
}
