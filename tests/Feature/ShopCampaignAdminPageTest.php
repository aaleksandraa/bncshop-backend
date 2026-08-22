<?php

namespace Tests\Feature;

use App\Models\ShopCampaign;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShopCampaignAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_campaigns_admin_list_page_loads(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'Admin',
            'email' => 'shop-campaigns-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('Admin');

        ShopCampaign::factory()->create([
            'name' => 'Back to school',
            'slug' => 'back-to-school',
        ]);

        $this->actingAs($admin)
            ->get('/admin/shop-campaigns')
            ->assertOk();
    }
}
