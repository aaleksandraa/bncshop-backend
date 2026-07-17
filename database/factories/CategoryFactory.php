<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'external_category_id' => (string) Str::uuid(),
            'name' => $name,
            'full_slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'status' => 'active',
        ];
    }
}
