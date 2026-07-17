<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_category_includes_products_from_subcategories(): void
    {
        $parent = Category::factory()->create([
            'full_slug' => 'racunari',
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'full_slug' => 'racunari/laptopi',
            'parent_id' => $parent->id,
        ]);

        Product::factory()->create([
            'category_id' => $child->id,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Laptop iz podkategorije',
        ]);

        $response = $this->getJson('/api/v1/products?category=racunari');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Laptop iz podkategorije');
    }

    public function test_klima_grijanje_lists_klime_before_grijanje_tijela(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Klima i grijanje',
            'full_slug' => 'klima-grijanje',
            'parent_id' => null,
        ]);

        $grijanje = Category::factory()->create([
            'name' => 'Grijanje tijela',
            'full_slug' => 'klima-grijanje/grijanje-tijela',
            'parent_id' => $parent->id,
        ]);

        $klime = Category::factory()->create([
            'name' => 'Klime',
            'full_slug' => 'klima-grijanje/klime',
            'parent_id' => $parent->id,
        ]);

        Product::factory()->create([
            'category_id' => $grijanje->id,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Grijač A',
            'created_at' => now()->subDay(),
        ]);

        Product::factory()->create([
            'category_id' => $klime->id,
            'is_public' => true,
            'status' => 'active',
            'name' => 'Klima A',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/products?category=klima-grijanje&sort=newest');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Klima A');
        $response->assertJsonPath('data.1.name', 'Grijač A');
    }
}
