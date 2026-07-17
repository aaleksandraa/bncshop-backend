<?php

namespace Database\Factories;

use App\Models\ShippingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingRuleFactory extends Factory
{
    protected $model = ShippingRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => 'global',
            'fixed_fee' => 10,
            'free_threshold' => 100,
            'pickup_enabled' => true,
            'is_active' => true,
            'priority' => 0,
        ];
    }
}
