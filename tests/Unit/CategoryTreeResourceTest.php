<?php

namespace Tests\Unit;

use App\Http\Resources\CategoryTreeResource;
use App\Models\Category;
use App\Models\CategorySeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exposes_display_name_on_frontend_name_field(): void
    {
        $category = Category::query()->create([
            'external_category_id' => (string) fake()->uuid(),
            'name' => 'Laptopi API',
            'display_name' => 'Prijenosna računala',
            'full_slug' => 'racunari/laptopi',
            'status' => 'active',
            'depth' => 1,
            'system' => true,
        ]);

        CategorySeo::query()->create([
            'category_id' => $category->id,
            'meta_title' => 'Laptopi BiH',
            'meta_description' => 'Najbolji laptopi.',
            'h1' => 'Laptop računari',
        ]);

        $category->load('seo');

        $payload = (new CategoryTreeResource($category))->resolve();

        $this->assertSame('Prijenosna računala', $payload['name']);
        $this->assertSame('Laptopi API', $payload['source_name']);
        $this->assertSame('Laptop računari', $payload['seo']['h1']);
    }

    public function test_has_complete_seo_requires_meta_and_short_description(): void
    {
        $category = Category::query()->create([
            'external_category_id' => (string) fake()->uuid(),
            'name' => 'Monitori',
            'full_slug' => 'monitori',
            'short_description' => 'Kratki opis.',
            'status' => 'active',
            'system' => true,
        ]);

        $this->assertFalse($category->hasCompleteSeo());

        CategorySeo::query()->create([
            'category_id' => $category->id,
            'meta_title' => 'Monitori',
            'meta_description' => 'Opis monitora.',
        ]);

        $category->load('seo');

        $this->assertTrue($category->fresh(['seo'])->hasCompleteSeo());
    }
}
