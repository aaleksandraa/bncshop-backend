<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerCatalogProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_seller_can_list_and_manage_catalog_product_images(): void
    {
        Storage::fake('public');

        $seller = $this->createSeller();
        $category = Category::factory()->create(['name' => 'Monitori']);

        $catalogProduct = Product::factory()->create([
            'import_source' => 'a1',
            'name' => 'A1 monitor',
            'category_id' => $category->id,
            'available_stock' => 3,
        ]);
        Product::factory()->create([
            'import_source' => 'eline',
            'name' => 'eLine laptop',
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->getJsonStateful('/api/v1/seller/catalog-products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A1 monitor')
            ->assertJsonPath('data.0.available_stock', 3);

        $this->getJsonStateful('/api/v1/seller/catalog-products/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Monitori');

        $upload = $this->postMultipartStateful("/api/v1/seller/catalog-products/{$catalogProduct->id}/images", [
            'image' => UploadedFile::fake()->image('monitor.jpg'),
            'is_primary' => true,
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.images.0.is_primary', true);

        $this->patchJsonStateful("/api/v1/seller/catalog-products/{$catalogProduct->id}", [
                'description' => 'Novi opis',
            ])
            ->assertUnprocessable();

        $this->patchJsonStateful("/api/v1/seller/products/{$catalogProduct->id}", [
                'description' => 'Novi opis',
            ])
            ->assertNotFound();
    }

    public function test_seller_can_filter_catalog_products(): void
    {
        $seller = $this->createSeller();
        $category = Category::factory()->create(['name' => 'Laptopi']);

        $inStock = Product::factory()->create([
            'import_source' => 'a1',
            'name' => 'Na stanju',
            'category_id' => $category->id,
            'available_stock' => 4,
            'updated_at' => now()->subDay(),
        ]);
        $outOfStock = Product::factory()->create([
            'import_source' => 'a1',
            'name' => 'Bez zalihe',
            'category_id' => $category->id,
            'available_stock' => 0,
            'updated_at' => now(),
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->getJsonStateful('/api/v1/seller/catalog-products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $inStock->id)
            ->assertJsonPath('data.1.id', $outOfStock->id);

        $this->getJsonStateful('/api/v1/seller/catalog-products?in_stock=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $outOfStock->id);
    }

    private function createSeller(): User
    {
        $role = Role::findOrCreate('Prodavac');

        $seller = User::createAccount([
            'name' => 'Test Prodavac',
            'email' => 'seller-catalog@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $seller->assignRole($role);

        return $seller;
    }
}
