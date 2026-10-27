<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Eline\ElineSupport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditElineProductKeepsIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_an_eline_product_does_not_replace_its_external_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'Catalog Admin',
            'email' => 'eline-identity@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('Super Admin');

        $externalId = ElineSupport::externalProductId('2251');
        $product = Product::factory()->create([
            'external_product_id' => $externalId,
            'import_source' => 'eline',
            'eline_sifra' => '2251',
            'sku' => '2251',
            'is_refurbished' => true,
            'is_new' => false,
            'available_stock' => 3,
            'api_stock' => 3,
        ]);

        Livewire::actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $product->fresh();

        $this->assertSame($externalId, $fresh->external_product_id);
        $this->assertSame('eline', $fresh->import_source);
        $this->assertSame('2251', $fresh->eline_sifra);
        $this->assertTrue($fresh->is_refurbished);
        $this->assertSame(3, $fresh->available_stock);
    }
}
