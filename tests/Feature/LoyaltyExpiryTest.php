<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoyaltyExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_months_after_earn_expires_old_points(): void
    {
        SystemSetting::query()->create([
            'key' => 'loyalty',
            'value' => [
                'enabled' => true,
                'expiry_mode' => 'months_after_earn',
                'expiry_months' => 12,
            ],
            'group' => 'loyalty',
        ]);

        $user = User::createAccount([
            'name' => 'Expire User',
            'email' => 'expire@test.test',
            'password' => Hash::make('password'),
            'is_customer' => true,
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'loyalty_points_balance' => 40,
        ]);

        LoyaltyTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => 40,
            'balance_after' => 40,
            'order_id' => null,
            'description' => 'Test earn',
            'created_at' => now()->subMonths(13),
        ]);

        $result = app(LoyaltyService::class)->expirePoints();

        $this->assertSame(40, $result['expired_points']);
        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
    }
}
