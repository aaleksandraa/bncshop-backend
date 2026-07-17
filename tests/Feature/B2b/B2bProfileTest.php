<?php

namespace Tests\Feature\B2b;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\B2b\Concerns\CreatesB2bCustomers;
use Tests\TestCase;

class B2bProfileTest extends TestCase
{
    use CreatesB2bCustomers;
    use RefreshDatabase;

    public function test_b2b_customer_can_view_profile(): void
    {
        [$user] = $this->createB2bUser('profile@test.test');
        $this->loginB2bUser($user);

        $this->getJsonStateful('/api/v1/b2b/profile')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Test d.o.o.')
            ->assertJsonPath('data.email', 'profile@test.test');
    }

    public function test_b2b_customer_can_update_contact_fields(): void
    {
        [$user, $customer] = $this->createB2bUser('profile2@test.test');
        $this->loginB2bUser($user);

        $this->patchJsonStateful('/api/v1/b2b/profile', [
            'phone' => '062999999',
            'company_address' => 'Nova adresa 2',
            'pdv_number' => '200111222',
        ])->assertOk()
            ->assertJsonPath('data.phone', '062999999')
            ->assertJsonPath('data.company_address', 'Nova adresa 2')
            ->assertJsonPath('data.company_name', 'Test d.o.o.')
            ->assertJsonPath('data.jib', $customer->jib);

        $this->assertDatabaseHas('b2b_customers', [
            'id' => $customer->id,
            'company_name' => 'Test d.o.o.',
            'jib' => $customer->jib,
            'phone' => '062999999',
            'company_address' => 'Nova adresa 2',
        ]);
    }

    public function test_b2b_customer_cannot_change_company_name_or_jib(): void
    {
        [$user, $customer] = $this->createB2bUser('profile3@test.test');
        $originalJib = $customer->jib;
        $this->loginB2bUser($user);

        $this->patchJsonStateful('/api/v1/b2b/profile', [
            'company_name' => 'Hacked firma d.o.o.',
            'jib' => '9999999999999',
            'phone' => '061222333',
        ])->assertOk()
            ->assertJsonPath('data.company_name', 'Test d.o.o.')
            ->assertJsonPath('data.jib', $originalJib)
            ->assertJsonPath('data.phone', '061222333');

        $this->assertDatabaseHas('b2b_customers', [
            'id' => $customer->id,
            'company_name' => 'Test d.o.o.',
            'jib' => $originalJib,
        ]);
    }
}
