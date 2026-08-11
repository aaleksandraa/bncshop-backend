<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function useAppEnvironment(string $environment): void
    {
        $this->app['env'] = $environment;
    }

    public function test_horizon_api_denied_for_guest_in_production(): void
    {
        $this->useAppEnvironment('production');

        $response = $this->getJson('/horizon/api/stats');

        $response->assertUnauthorized();
    }

    public function test_horizon_api_denied_for_admin_without_super_admin_role(): void
    {
        $this->useAppEnvironment('production');

        $user = User::createAccount([
            'name' => 'Admin User',
            'email' => 'admin-horizon@test.test',
            'password' => 'AdminHorizon123!',
            'email_verified_at' => now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);
        $user->syncRoles(['Admin']);

        $response = $this->actingAs($user)->getJson('/horizon/api/stats');

        $response->assertForbidden();
    }

    public function test_horizon_api_allowed_for_super_admin(): void
    {
        $this->useAppEnvironment('production');

        $user = User::createAccount([
            'name' => 'Super Admin User',
            'email' => 'super-admin-horizon@test.test',
            'password' => 'SuperAdmin123!',
            'email_verified_at' => now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);
        $user->syncRoles(['Super Admin']);

        $response = $this->actingAs($user)->getJson('/horizon/api/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'failedJobs',
            'jobsPerMinute',
            'status',
        ]);
    }

    public function test_horizon_api_allowed_in_local_env_without_super_admin_role(): void
    {
        $this->useAppEnvironment('local');

        $user = User::createAccount([
            'name' => 'Admin User Local',
            'email' => 'admin-local-horizon@test.test',
            'password' => 'AdminLocal123!',
            'email_verified_at' => now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);
        $user->syncRoles(['Admin']);

        $response = $this->actingAs($user)->getJson('/horizon/api/stats');

        $response->assertOk();
    }
}
