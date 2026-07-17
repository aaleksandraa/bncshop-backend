<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bCatalogTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_categories_are_returned_for_authenticated_customer(): void
    {
        B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        [$user] = $this->createB2bUser('catalog@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'laptopi');
    }

    public function test_categories_response_is_cached(): void
    {
        Cache::flush();

        B2bCategory::query()->create([
            'name' => 'Monitori',
            'slug' => 'monitori',
            'is_active' => true,
        ]);

        [$user] = $this->createB2bUser('cache@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/categories')->assertOk();

        B2bCategory::query()->create([
            'name' => 'Tastature',
            'slug' => 'tastature',
            'is_active' => true,
        ]);

        $this->getJsonStateful('/api/v1/b2b/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
