<?php

namespace Tests\Unit;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Support\ProductAdminSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_products_by_case_insensitive_name(): void
    {
        Product::factory()->create([
            'name' => 'DELL Latitude 5520',
            'sku' => 'DELL-5520',
        ]);

        $results = ProductAdminSearch::optionsForSearch('dell');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('DELL Latitude 5520', array_values($results)[0]);
    }

    public function test_finds_products_by_manufacturer_name(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Dell']);

        Product::factory()->create([
            'name' => 'Latitude 5520',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $results = ProductAdminSearch::optionsForSearch('dell');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Latitude 5520', array_values($results)[0]);
    }
}
