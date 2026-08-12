<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Sync\CategoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryImporterMarginTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_category_margin_from_api_payload(): void
    {
        $importer = app(CategoryImporter::class);

        $importer->upsertOne([
            'categoryId' => 'cat-margin-1',
            'name' => 'Access Point',
            'slug' => 'it-oprema/mreza/access-point',
            'marginId' => 'margin-1',
            'marginName' => 'Mrežna oprema',
            'marginPercentage' => 30,
        ]);

        $category = Category::query()->where('external_category_id', 'cat-margin-1')->first();

        $this->assertNotNull($category);
        $this->assertSame('Mrežna oprema', $category->margin_name);
        $this->assertSame(30.0, (float) $category->margin_percentage);
        $this->assertFalse($category->margin_locked);
    }

    public function test_does_not_overwrite_locked_category_margin(): void
    {
        $category = Category::factory()->create([
            'external_category_id' => 'cat-margin-locked',
            'name' => 'Access Point',
            'margin_percentage' => 22,
            'margin_locked' => true,
        ]);

        app(CategoryImporter::class)->upsertOne([
            'categoryId' => 'cat-margin-locked',
            'name' => 'Access Point',
            'slug' => $category->full_slug,
            'marginId' => 'margin-1',
            'marginName' => 'Mrežna oprema',
            'marginPercentage' => 30,
        ]);

        $category->refresh();

        $this->assertSame(22.0, (float) $category->margin_percentage);
        $this->assertTrue($category->margin_locked);
        $this->assertSame('Mrežna oprema', $category->margin_name);
    }
}
