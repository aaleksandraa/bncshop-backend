<?php

namespace Tests\Feature\B2b;

use App\Filament\B2b\Resources\B2bProductResource\Pages\CreateB2bProduct;
use App\Filament\B2b\Resources\B2bProductResource\Pages\EditB2bProduct;
use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bOrder;
use App\Models\B2bProduct;
use App\Models\B2bStockMovement;
use App\Models\User;
use App\Services\B2b\B2bOrderService;
use App\Support\B2bOrderStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bStockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_records_who_reduced_stock_and_delivery_does_not(): void
    {
        Mail::fake();

        [$user, $customer, $product] = $this->seedCustomerWithCart(stock: 5, quantity: 2);

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->postJsonStateful('/api/v1/b2b/checkout', [
            'shipping_address' => 'Dostava ulica 5',
        ])->assertCreated();

        $order = B2bOrder::query()->firstOrFail();

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('b2b_stock_movements', [
            'b2b_product_id' => $product->id,
            'delta' => -2,
            'quantity_before' => 5,
            'quantity_after' => 3,
            'type' => B2bStockMovement::TYPE_ORDER,
            'b2b_order_id' => $order->id,
            'b2b_customer_id' => $customer->id,
            'summary' => "Narudžba {$order->order_number} — Checkout d.o.o.",
        ]);

        app(B2bOrderService::class)->updateStatus($order, B2bOrderStatus::ISPORUCENA);

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame(1, B2bStockMovement::query()->count());

        app(B2bOrderService::class)->updateStatus($order->fresh(), B2bOrderStatus::OTKAZANA);

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('b2b_stock_movements', [
            'b2b_product_id' => $product->id,
            'delta' => 2,
            'quantity_before' => 3,
            'quantity_after' => 5,
            'type' => B2bStockMovement::TYPE_ORDER_CANCELLED,
            'summary' => "Otkazana narudžba {$order->order_number} — Checkout d.o.o.",
        ]);
    }

    public function test_admin_stock_change_records_signed_delta(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-stock-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Monitor',
            'slug' => 'monitor',
            'sku' => 'MON-1',
            'regular_price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $this->bootB2bPanel($admin);

        Livewire::test(EditB2bProduct::class, ['record' => $product->id])
            ->set('data.stock_quantity', 12)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(12, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('b2b_stock_movements', [
            'b2b_product_id' => $product->id,
            'delta' => 2,
            'quantity_before' => 10,
            'quantity_after' => 12,
            'type' => B2bStockMovement::TYPE_ADMIN,
            'user_id' => $admin->id,
            'summary' => 'Admin dodao — B2B Admin',
        ]);

        Livewire::test(EditB2bProduct::class, ['record' => $product->id])
            ->set('data.name', 'Monitor 24')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, B2bStockMovement::query()->count());

        Livewire::test(EditB2bProduct::class, ['record' => $product->id])
            ->set('data.stock_quantity', 11)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('b2b_stock_movements', [
            'b2b_product_id' => $product->id,
            'delta' => -1,
            'quantity_before' => 12,
            'quantity_after' => 11,
            'summary' => 'Admin smanjio — B2B Admin',
        ]);
    }

    public function test_creating_a_product_records_opening_stock(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-stock-create@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi-create',
            'is_active' => true,
        ]);

        $this->bootB2bPanel($admin);

        Livewire::test(CreateB2bProduct::class)
            ->set('data', [
                'b2b_category_id' => $category->id,
                'name' => 'Tastatura',
                'slug' => 'tastatura',
                'sku' => 'KEY-1',
                'description' => null,
                'regular_price' => 40,
                'sale_price' => null,
                'exclude_customer_discount' => false,
                'stock_quantity' => 4,
                'sort_order' => 0,
                'is_active' => true,
                'images' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = B2bProduct::query()->where('slug', 'tastatura')->firstOrFail();

        $this->assertDatabaseHas('b2b_stock_movements', [
            'b2b_product_id' => $product->id,
            'delta' => 4,
            'quantity_before' => 0,
            'quantity_after' => 4,
            'type' => B2bStockMovement::TYPE_ADMIN,
            'summary' => 'Admin dodao — B2B Admin',
        ]);
    }

    private function bootB2bPanel(User $admin): void
    {
        Livewire::actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('b2b-admin'));
        Filament::bootCurrentPanel();
    }

    /**
     * @return array{0: User, 1: B2bCustomer, 2: B2bProduct}
     */
    private function seedCustomerWithCart(int $stock, int $quantity): array
    {
        $user = User::createAccount([
            'name' => 'B2B Kupac',
            'email' => 'stock-checkout@test.test',
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Checkout d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890999',
            'phone' => '061333333',
            'is_active' => true,
            'discount_percent' => 10,
        ]);

        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat-stock',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Proizvod',
            'slug' => 'proizvod-stock',
            'sku' => 'SKU-STOCK',
            'regular_price' => 100,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        $cart = B2bCart::query()->create([
            'b2b_customer_id' => $customer->id,
        ]);

        B2bCartItem::query()->create([
            'b2b_cart_id' => $cart->id,
            'b2b_product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return [$user, $customer, $product];
    }
}
