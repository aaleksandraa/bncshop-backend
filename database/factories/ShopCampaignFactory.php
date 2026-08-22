<?php

namespace Database\Factories;

use App\Models\ShopCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopCampaign>
 */
class ShopCampaignFactory extends Factory
{
    protected $model = ShopCampaign::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'badge_path' => 'campaigns/badges/test-badge.png',
            'badge_alt' => $name,
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'targeting_mode' => ShopCampaign::TARGETING_PRODUCTS,
            'include_subcategories' => true,
            'has_landing_page' => true,
            'page_title' => $name,
            'page_description' => fake()->sentence(),
            'hero_image_path' => null,
            'meta_title' => $name,
            'meta_description' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'ends_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
        ]);
    }

    public function withoutLandingPage(): static
    {
        return $this->state(fn (): array => [
            'has_landing_page' => false,
        ]);
    }

    public function byCategories(): static
    {
        return $this->state(fn (): array => [
            'targeting_mode' => ShopCampaign::TARGETING_CATEGORIES,
        ]);
    }
}
