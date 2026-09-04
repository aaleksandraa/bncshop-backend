<?php

namespace Tests\Feature\B2b;

use App\Filament\B2b\Resources\B2bProductResource\Pages\CreateB2bProduct;
use App\Models\B2bAttributeDefinition;
use App\Models\B2bCategory;
use App\Models\B2bProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bProductAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_admin_can_create_a_product_with_attributes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-product-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);

        $ram = B2bAttributeDefinition::query()->create([
            'name' => 'RAM',
            'slug' => 'ram',
            'input_type' => B2bAttributeDefinition::INPUT_TEXT,
            'is_filterable' => true,
            'is_active' => true,
        ]);
        $ram->categories()->attach($category->id, ['sort_order' => 1]);

        Livewire::actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('b2b-admin'));
        Filament::bootCurrentPanel();

        $component = Livewire::test(CreateB2bProduct::class);

        $component
            ->assertStatus(200)
            ->set('data', [
                'b2b_category_id' => $category->id,
                'name' => 'Test laptop',
                'slug' => 'test-laptop',
                'sku' => 'TEST-001',
                'description' => '<p>Testni proizvod</p>',
                'regular_price' => 222,
                'sale_price' => null,
                'exclude_customer_discount' => false,
                'stock_quantity' => 2,
                'sort_order' => 0,
                'is_active' => true,
                'attr_ram' => '16 GB',
                'images' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = B2bProduct::query()->where('slug', 'test-laptop')->firstOrFail();

        $this->assertDatabaseHas('b2b_product_attribute_values', [
            'b2b_product_id' => $product->id,
            'b2b_attribute_definition_id' => $ram->id,
            'value' => '16 GB',
        ]);
    }
}
