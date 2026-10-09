<?php

namespace Tests\Feature\Ananas;

use App\Filament\Pages\AnanasSyncSettingsPage;
use App\Models\AnanasCategoryMapping;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AnanasSyncSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_opens_without_scanning_the_catalog(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'Ananas Admin',
            'email' => 'ananas-settings@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('Super Admin');

        $category = Category::factory()->create(['name' => 'Laptopi', 'display_name' => 'Laptopi']);
        AnanasCategoryMapping::query()->create([
            'category_id' => $category->id,
            'ananas_product_type' => 'ITShop',
            'ananas_category' => 'Gaming laptopi',
            'is_enabled' => true,
            'include_descendants' => true,
        ]);

        Livewire::actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        Livewire::test(AnanasSyncSettingsPage::class)
            ->assertStatus(200)
            ->assertSet('eligibilityLoaded', false)
            ->assertSee('Nije učitano')
            ->assertSee('Gaming laptopi')
            ->assertSee('API pristup (Ananas Merchant API)');
    }
}
