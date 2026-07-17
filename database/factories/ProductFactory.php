<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'external_product_id' => (string) Str::uuid(),
            'manufacturer_id' => Manufacturer::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'regular_price' => fake()->randomFloat(2, 10, 1000),
            'display_price' => fake()->randomFloat(2, 10, 1000),
            'api_price' => fake()->randomFloat(2, 10, 1000),
            'api_final_price' => fake()->randomFloat(2, 10, 1000),
            'api_stock' => fake()->numberBetween(0, 50),
            'available_stock' => fake()->numberBetween(0, 50),
            'is_public' => true,
            'status' => 'active',
        ];
    }
}
