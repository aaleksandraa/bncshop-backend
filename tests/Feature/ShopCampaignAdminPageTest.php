<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopCampaign;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_shop_campaigns_admin_create_page_loads_with_many_products(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'Admin',
            'email' => 'shop-campaigns-create@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('Admin');

        Product::factory()->count(250)->create([
            'is_public' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/admin/shop-campaigns/create')
            ->assertOk();
    }

    public function test_admin_media_preview_streams_stored_file_for_authenticated_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'Admin',
            'email' => 'media-preview-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('Admin');

        Storage::disk('public')->put('campaigns/badges/preview-test.webp', 'webp-bytes');

        $guestStatus = $this->get('/admin/media-preview?path=campaigns/badges/preview-test.webp')->status();
        $this->assertContains($guestStatus, [302, 401, 403]);

        $response = $this->actingAs($admin)
            ->get('/admin/media-preview?path=campaigns/badges/preview-test.webp')
            ->assertOk();

        $this->assertSame('webp-bytes', $response->streamedContent());

        $this->actingAs($admin)
            ->get('/admin/media-preview?path=../.env')
            ->assertNotFound();
    }
}
