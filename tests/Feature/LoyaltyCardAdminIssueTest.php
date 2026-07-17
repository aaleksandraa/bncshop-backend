<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoyaltyCardAdminIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_for_loyalty_card_scope_excludes_customers_with_active_card(): void
    {
        $user = User::createAccount([
            'name' => 'Eligible',
            'email' => 'eligible@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create(['user_id' => $user->id]);

        $this->assertTrue(
            Customer::query()->eligibleForLoyaltyCard()->whereKey($customer->id)->exists()
        );

        LoyaltyCard::query()->create([
            'customer_id' => $customer->id,
            'card_number' => 'BNC-00000001',
            'status' => 'active',
            'issued_at' => now(),
        ]);

        $this->assertFalse(
            Customer::query()->eligibleForLoyaltyCard()->whereKey($customer->id)->exists()
        );
    }

    public function test_eligible_for_loyalty_card_scope_excludes_customers_without_email(): void
    {
        $user = User::createAccount([
            'name' => 'No Email',
            'email' => '',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create(['user_id' => $user->id]);

        $this->assertFalse(
            Customer::query()->eligibleForLoyaltyCard()->whereKey($customer->id)->exists()
        );
    }
}
