<?php

namespace Tests\Feature\B2b;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bAdminUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_admin_user_created_like_admin_panel_can_authenticate_and_access_panel(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = User::createAccount([
            'name' => 'Panel B2B Admin',
            'email' => 'panel-b2b-admin@test.test',
            'password' => 'PanelAdmin123!',
            'email_verified_at' => now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);

        $user->syncRoles([Role::findByName('B2B Admin')->name]);

        $this->assertTrue(Auth::attempt([
            'email' => 'panel-b2b-admin@test.test',
            'password' => 'PanelAdmin123!',
        ]));

        $authenticated = Auth::user();
        $this->assertInstanceOf(User::class, $authenticated);
        $this->assertTrue($authenticated->canAccessPanel(Filament::getPanel('b2b-admin')));
        $this->assertFalse($authenticated->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_user_without_b2b_admin_role_cannot_access_b2b_admin_panel(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = User::createAccount([
            'name' => 'Manager Only',
            'email' => 'manager-only@test.test',
            'password' => Hash::make('Manager123!'),
            'email_verified_at' => now(),
            'is_customer' => false,
            'is_b2b_customer' => false,
        ]);

        $user->syncRoles(['Manager']);

        $this->assertTrue(Auth::attempt([
            'email' => 'manager-only@test.test',
            'password' => 'Manager123!',
        ]));

        $this->assertFalse(Auth::user()->canAccessPanel(Filament::getPanel('b2b-admin')));
    }
}
