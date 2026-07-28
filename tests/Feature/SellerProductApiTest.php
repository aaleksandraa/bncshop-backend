<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategory;
use App\Models\ElineCategoryMapping;
use App\Models\Product;
use App\Models\User;
use App\Services\Eline\ElineProductImporter;
use App\Services\Eline\ElineSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_seller_can_list_and_update_eline_products(): void
    {
        Storage::fake('public');

        $seller = $this->createSeller();
        $elineProduct = $this->createElineProduct([
            'name' => 'eLine laptop',
            'regular_price' => 500,
            'display_price' => 500,
            'description' => 'Original opis',
        ]);
        Product::factory()->create([
            'import_source' => 'a1',
            'name' => 'A1 proizvod',
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.can_edit_eline_products', true);

        $this->getJsonStateful('/api/v1/seller/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'eLine laptop')
            ->assertJsonPath('data.0.available_stock', $elineProduct->available_stock);

        $this->patchJsonStateful("/api/v1/seller/products/{$elineProduct->id}", [
                'description' => 'Prodavačev opis',
                'short_description' => 'Kratak opis',
                'sale_price' => 450,
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Prodavačev opis')
            ->assertJsonPath('data.sale_price', 450)
            ->assertJsonPath('data.on_sale', true);

        $elineProduct->refresh();
        $this->assertSame('Prodavačev opis', $elineProduct->description);
        $this->assertTrue($elineProduct->on_sale);
        $this->assertSame(450.0, (float) $elineProduct->display_price);
    }

    public function test_seller_can_filter_and_sort_eline_products(): void
    {
        $seller = $this->createSeller();
        $laptopCategory = Category::factory()->create(['name' => 'Laptopi']);
        $monitorCategory = Category::factory()->create(['name' => 'Monitori']);

        $inStockLaptop = $this->createElineProduct([
            'name' => 'Laptop na stanju',
            'category_id' => $laptopCategory->id,
            'available_stock' => 5,
            'updated_at' => now()->subDay(),
        ]);
        $outOfStockLaptop = $this->createElineProduct([
            'name' => 'Laptop bez zalihe',
            'category_id' => $laptopCategory->id,
            'available_stock' => 0,
            'updated_at' => now(),
        ]);
        $inStockMonitor = $this->createElineProduct([
            'name' => 'Monitor na stanju',
            'category_id' => $monitorCategory->id,
            'available_stock' => 2,
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->getJsonStateful('/api/v1/seller/products/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Laptopi');

        $this->getJsonStateful('/api/v1/seller/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $inStockMonitor->id)
            ->assertJsonPath('data.1.id', $inStockLaptop->id)
            ->assertJsonPath('data.2.id', $outOfStockLaptop->id);

        $this->getJsonStateful('/api/v1/seller/products?category_id='.$laptopCategory->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJsonStateful('/api/v1/seller/products?in_stock=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJsonStateful('/api/v1/seller/products?in_stock=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $outOfStockLaptop->id);
    }

    public function test_seller_cannot_update_non_eline_product(): void
    {
        $seller = $this->createSeller();
        $product = Product::factory()->create(['import_source' => 'a1']);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->patchJsonStateful("/api/v1/seller/products/{$product->id}", [
                'description' => 'Novi opis',
            ])
            ->assertNotFound();
    }

    public function test_seller_without_product_permission_gets_forbidden(): void
    {
        $role = Role::findOrCreate('Warehouse');
        $user = User::createAccount([
            'name' => 'Skladištar',
            'email' => 'warehouse@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $user->assignRole($role);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->getJsonStateful('/api/v1/seller/products')
            ->assertForbidden();
    }

    public function test_seller_can_upload_and_delete_product_image(): void
    {
        Storage::fake('public');

        $seller = $this->createSeller();
        $product = $this->createElineProduct();

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $upload = $this->postMultipartStateful("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('laptop.jpg'),
            'is_primary' => true,
        ]);

        $upload->assertOk()
            ->assertJsonPath('data.images.0.is_primary', true);

        $imageId = $upload->json('data.images.0.id');

        $this->deleteJsonStateful("/api/v1/seller/products/{$product->id}/images/{$imageId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.images');
    }

    public function test_eline_sync_preserves_locked_description(): void
    {
        $seller = $this->createSeller();
        $product = $this->createElineProduct([
            'eline_sifra' => 'EL-100',
            'external_product_id' => ElineSupport::externalProductId('EL-100'),
            'description' => 'ERP opis',
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->patchJsonStateful("/api/v1/seller/products/{$product->id}", [
                'description' => 'Ručno uređen opis',
            ])
            ->assertOk();

        $source = ApiSource::query()->create([
            'name' => 'eLine ERP',
            'target_system_code' => 'eline',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $elineCategory = ElineCategory::query()->create([
            'name' => 'Laptopi',
            'product_count' => 1,
        ]);

        ElineCategoryMapping::query()->create([
            'eline_category_id' => $elineCategory->id,
            'category_id' => $category->id,
            'product_condition' => ElineCategoryMapping::CONDITION_REFURBISHED,
            'margin_percentage' => 0,
            'is_enabled' => true,
        ]);

        app(ElineProductImporter::class)->importMany(
            collect([[
                'sifra' => 'EL-100',
                'eline_category' => 'Laptopi',
                'naziv' => 'Laptop test',
                'opis' => 'Novi ERP opis',
                'mpc' => 600,
                'stanje' => 2,
                'aktivan' => 1,
                'price_aktivan' => 1,
            ]]),
            ElineCategoryMapping::query()->with('elineCategory')->get()->keyBy(
                fn (ElineCategoryMapping $mapping): string => (string) $mapping->elineCategory?->name,
            ),
            $source,
        );

        $product->refresh();

        $this->assertSame('Ručno uređen opis', $product->description);
        $this->assertSame(600.0, (float) $product->regular_price);
    }

    public function test_seller_uploaded_image_is_exposed_with_absolute_url_on_product_api(): void
    {
        Storage::fake('public');

        $seller = $this->createSeller();
        $product = $this->createElineProduct([
            'slug' => 'eline-laptop-test',
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $seller->email,
            'password' => 'password123',
        ])->assertOk();

        $this->postMultipartStateful("/api/v1/seller/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('laptop.jpg'),
            'is_primary' => true,
        ])->assertOk();

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();

        $imageUrl = $response->json('data.default_image.url');

        $this->assertIsString($imageUrl);
        $this->assertStringStartsWith('http', $imageUrl);
        $this->assertStringContainsString('/storage/', $imageUrl);
    }

    private function createSeller(): User
    {
        $role = Role::findOrCreate('Prodavac');

        $seller = User::createAccount([
            'name' => 'Test Prodavac',
            'email' => 'seller-products@test.test',
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $seller->assignRole($role);

        return $seller;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createElineProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'import_source' => 'eline',
            'external_product_id' => ElineSupport::externalProductId('EL-'.fake()->unique()->numberBetween(1000, 9999)),
            'eline_sifra' => 'EL-'.fake()->unique()->numberBetween(1000, 9999),
            'regular_price' => 100,
            'display_price' => 100,
            'api_price' => 100,
            'api_final_price' => 100,
        ], $attributes));
    }
}
