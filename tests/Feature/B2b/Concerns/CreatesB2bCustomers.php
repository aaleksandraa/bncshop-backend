<?php

namespace Tests\Feature\B2b\Concerns;

use App\Models\B2bCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesB2bCustomers
{
    /**
     * @return array{0: User, 1: B2bCustomer}
     */
    protected function createB2bUser(
        string $email = 'b2b@test.test',
        float $discountPercent = 5,
    ): array {
        $user = User::createAccount([
            'name' => 'B2B User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        $customer = B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Adresa 1',
            'jib' => substr(str_pad((string) random_int(1, 9999999999999), 13, '0', STR_PAD_LEFT), 0, 13),
            'phone' => '061111111',
            'is_active' => true,
            'discount_percent' => $discountPercent,
        ]);

        return [$user, $customer];
    }

    protected function loginB2bUser(User $user): void
    {
        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();
    }
}
