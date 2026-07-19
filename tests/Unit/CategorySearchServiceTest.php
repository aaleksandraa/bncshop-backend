<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Search\CategorySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_category_before_broader_parent(): void
    {
        $periferija = Category::factory()->create([
            'name' => 'Periferija',
            'full_slug' => 'it-oprema/periferija',
            'depth' => 1,
            'path' => '1',
        ]);

        Category::factory()->create([
            'name' => 'Monitori',
            'full_slug' => 'it-oprema/periferija/monitori',
            'parent_id' => $periferija->id,
            'depth' => 2,
            'path' => '1.2',
        ]);

        $results = app(CategorySearchService::class)->search('monitor', 5);

        $this->assertNotEmpty($results);
        $this->assertSame('Monitori', $results[0]['name']);
        $this->assertSame('it-oprema/periferija/monitori', $results[0]['full_slug']);
    }

    public function test_search_matches_refurbished_monitor_queries_to_specific_category(): void
    {
        Category::factory()->create([
            'name' => 'Monitori',
            'full_slug' => 'it-oprema/periferija/monitori',
            'depth' => 2,
            'path' => '1.2',
        ]);

        Category::factory()->create([
            'name' => 'Polovni monitori',
            'display_name' => 'Polovni monitori',
            'full_slug' => 'refurbished/monitori',
            'depth' => 2,
            'path' => '2.1',
        ]);

        $results = app(CategorySearchService::class)->search('polovni monitori', 5);

        $this->assertNotEmpty($results);
        $this->assertSame('Polovni monitori', $results[0]['name']);
        $this->assertSame('refurbished/monitori', $results[0]['full_slug']);
    }

    public function test_search_returns_empty_array_for_blank_query(): void
    {
        Category::factory()->create([
            'name' => 'Monitori',
            'full_slug' => 'monitori',
        ]);

        $this->assertSame([], app(CategorySearchService::class)->search('   ', 5));
    }
}
