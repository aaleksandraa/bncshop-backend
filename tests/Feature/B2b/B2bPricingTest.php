<?php

namespace Tests\Feature\B2b;

use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\User;
use App\Services\B2b\B2bPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class B2bPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_discount_is_applied(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product',
            'regular_price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $user = User::createAccount([
            'name' => 'B2B',
            'email' => 'b2b@test.test',
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Firma',
            'company_address' => 'Adresa',
            'jib' => '1234567890123',
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 10,
        ]);

        $pricing = app(B2bPricingService::class)->calculate($product, $customer);

        $this->assertSame(90.0, $pricing['final_price']);
        $this->assertSame(10.0, $pricing['customer_discount_percent']);
    }

    public function test_sale_price_beats_customer_discount(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product',
            'regular_price' => 100,
            'sale_price' => 80,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => User::createAccount([
                'name' => 'B2B',
                'email' => 'b2b@test.test',
                'password' => Hash::make('password123'),
                'is_b2b_customer' => true,
            ])->id,
            'company_name' => 'Firma',
            'company_address' => 'Adresa',
            'jib' => '1234567890123',
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => 10,
        ]);

        $pricing = app(B2bPricingService::class)->calculate($product, $customer);

        $this->assertSame(80.0, $pricing['final_price']);
    }

    public function test_exclude_customer_discount_flag(): void
    {
        $category = B2bCategory::query()->create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);

        $product = B2bProduct::query()->create([
            'b2b_category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product',
            'regular_price' => 100,
            'stock_quantity' => 10,
            'exclude_customer_discount' => true,
            'is_active' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => User::createAccount([
                'name' => 'B2B',
                'email' => 'b2b2@test.test',
                'password' => Hash::make('password123'),
                'is_b2b_customer' => true,
            ])->id,
            'company_name' => 'Firma',
            'company_address' => 'Adresa',
            'jib' => '1234567890124',
            'phone' => '061222222',
            'is_active' => true,
            'discount_percent' => 20,
        ]);

        $pricing = app(B2bPricingService::class)->calculate($product, $customer);

        $this->assertSame(100.0, $pricing['final_price']);
    }
}
