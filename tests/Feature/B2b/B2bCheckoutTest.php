<?php

namespace Tests\Feature\B2b;

use App\Mail\B2b\B2bOrderConfirmationCustomer;
use App\Mail\B2b\B2bOrderNotificationAdmin;
use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class B2bCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: B2bCustomer, 2: B2bProduct}
     */
    private function seedCustomerWithCart(int $stock = 5, int $quantity = 2): array
    {
        $user = User::createAccount([
            'name' => 'B2B Kupac',
            'email' => 'checkout@test.test',
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Checkout d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890123',
            'phone' => '061333333',
            'is_active' => true,
            'discount_percent' => 10,
        ]);

        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Proizvod',
            'slug' => 'proizvod',
            'sku' => 'SKU-001',
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

    public function test_checkout_snapshots_price_and_reduces_stock(): void
    {
        Mail::fake();

        config(['b2b.mail.admin_notification_email' => 'b2b@bncshop.ba']);

        [$user, $customer, $product] = $this->seedCustomerWithCart(stock: 5, quantity: 2);

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $response = $this->postJsonStateful('/api/v1/b2b/checkout', [
            'shipping_address' => 'Dostava ulica 5',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.unit_final_price', 90)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('b2b_orders', [
            'b2b_customer_id' => $customer->id,
            'shipping_address' => 'Dostava ulica 5',
        ]);

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('b2b_cart_items', 0);

        Mail::assertSent(B2bOrderConfirmationCustomer::class, function (B2bOrderConfirmationCustomer $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
        Mail::assertSent(B2bOrderNotificationAdmin::class, function (B2bOrderNotificationAdmin $mail): bool {
            return $mail->hasTo('b2b@bncshop.ba');
        });
        Mail::assertNotQueued(B2bOrderConfirmationCustomer::class);
        Mail::assertNotQueued(B2bOrderNotificationAdmin::class);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        Mail::fake();

        [$user] = $this->seedCustomerWithCart(stock: 1, quantity: 3);

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->postJsonStateful('/api/v1/b2b/checkout', [
            'shipping_address' => 'Dostava ulica 5',
        ])->assertStatus(422);
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $user = User::createAccount([
            'name' => 'B2B Kupac',
            'email' => 'empty@test.test',
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Prazna d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => '1234567890125',
            'phone' => '061444444',
            'is_active' => true,
        ]);

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->postJsonStateful('/api/v1/b2b/checkout', [
            'shipping_address' => 'Dostava ulica 5',
        ])->assertStatus(422);
    }
}
