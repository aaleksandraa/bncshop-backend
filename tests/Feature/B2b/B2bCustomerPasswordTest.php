<?php

namespace Tests\Feature\B2b;

use App\Models\B2bPasswordSetupToken;
use App\Models\User;
use App\Services\B2b\B2bCustomerProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class B2bCustomerPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_customer_with_password_can_login(): void
    {
        config(['turnstile.enabled' => false]);

        $payload = [
            'first_name' => 'Amir',
            'last_name' => 'Hadžić',
            'phone' => '061234567',
            'email' => 'firma-password@test.test',
            'company_name' => 'Test d.o.o.',
            'company_address' => 'Ulica 1, Sarajevo',
            'jib' => '1000000000001',
            'pdv_number' => '1234567890123',
        ];

        $customer = app(B2bCustomerProvisioner::class)->createCustomer([
            'name' => $payload['first_name'].' '.$payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'company_name' => $payload['company_name'],
            'company_address' => $payload['company_address'],
            'jib' => $payload['jib'],
            'pdv_number' => $payload['pdv_number'],
        ], sendPasswordEmail: false, password: 'AdminSet123!');

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $customer->user->email,
            'password' => 'AdminSet123!',
        ])->assertOk()
            ->assertJsonPath('data.user.company_name', 'Test d.o.o.');
    }

    public function test_customer_can_login_after_password_setup_token_flow(): void
    {
        config(['turnstile.enabled' => false]);

        $payload = [
            'first_name' => 'Lejla',
            'last_name' => 'Kovač',
            'phone' => '061234568',
            'email' => 'firma-setup@test.test',
            'company_name' => 'Setup d.o.o.',
            'company_address' => 'Ulica 2, Sarajevo',
            'jib' => '1000000000002',
        ];

        $customer = app(B2bCustomerProvisioner::class)->createCustomer([
            'name' => $payload['first_name'].' '.$payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'company_name' => $payload['company_name'],
            'company_address' => $payload['company_address'],
            'jib' => $payload['jib'],
        ], sendPasswordEmail: false);

        $plainToken = B2bPasswordSetupToken::createForUser($customer->user);

        $this->postJson('/api/v1/b2b/auth/set-password', [
            'token' => $plainToken,
            'password' => 'SetupFlow123!',
            'password_confirmation' => 'SetupFlow123!',
        ])->assertOk();

        $this->postJsonStateful('/api/v1/b2b/auth/login', [
            'email' => $customer->user->email,
            'password' => 'SetupFlow123!',
        ])->assertOk();
    }

    public function test_provisioner_set_customer_password_updates_hash(): void
    {
        $user = User::createAccount([
            'name' => 'B2B User',
            'email' => 'reset@test.test',
            'password' => Hash::make('old-password'),
            'is_b2b_customer' => true,
        ]);

        app(B2bCustomerProvisioner::class)->setCustomerPassword($user, 'NewPassword123!');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }
}
