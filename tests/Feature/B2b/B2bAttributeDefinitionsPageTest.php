<?php

namespace Tests\Feature\B2b;

use App\Models\B2bAttributeDefinition;
use App\Models\B2bCategory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use App\Models\User;

class B2bAttributeDefinitionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_attribute_definitions_list_page_loads(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-attrs-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $category = B2bCategory::query()->create([
            'name' => 'Monitori',
            'slug' => 'monitori',
            'is_active' => true,
        ]);

        $definition = B2bAttributeDefinition::query()->create([
            'name' => 'Rezolucija',
            'slug' => 'rezolucija',
            'input_type' => B2bAttributeDefinition::INPUT_SELECT,
            'is_filterable' => true,
            'is_active' => true,
        ]);

        $definition->categories()->attach($category->id, ['sort_order' => 1]);

        $this->actingAs($admin)
            ->get('/b2b-admin/b2b-attribute-definitions')
            ->assertOk();
    }

    public function test_b2b_attribute_definitions_edit_page_loads_with_category_tab(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-attrs-edit@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);

        $otherCategory = B2bCategory::query()->create([
            'name' => 'Monitori',
            'slug' => 'monitori',
            'is_active' => true,
        ]);

        $definition = B2bAttributeDefinition::query()->create([
            'name' => 'RAM',
            'slug' => 'ram',
            'input_type' => B2bAttributeDefinition::INPUT_SELECT,
            'is_filterable' => true,
            'is_active' => true,
        ]);

        $definition->categories()->attach($category->id, ['sort_order' => 1]);

        $this->actingAs($admin)
            ->get("/b2b-admin/b2b-attribute-definitions/{$definition->id}/edit")
            ->assertOk();

        $definition->categories()->attach($otherCategory->id, ['sort_order' => 2]);

        $this->assertDatabaseHas('b2b_category_attribute', [
            'b2b_attribute_definition_id' => $definition->id,
            'b2b_category_id' => $otherCategory->id,
            'sort_order' => 2,
        ]);
    }
}
