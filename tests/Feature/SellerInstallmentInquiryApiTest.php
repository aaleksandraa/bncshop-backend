<?php

namespace Tests\Feature;

use App\Mail\InstallmentInquiryNotification;
use App\Models\InstallmentInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellerInstallmentInquiryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        config(['bnc.frontend_url' => 'https://bnc.ba']);
    }

    public function test_seller_can_list_view_and_update_installment_inquiries(): void
    {
        $this->loginSeller('seller-rate@test.test');

        $inquiry = $this->createInquiry([
            'first_name' => 'Ana',
            'last_name' => 'Anic',
            'product_name' => 'Laptop X',
            'status' => 'nova',
        ]);

        $this->getJsonStateful('/api/v1/seller/installment-inquiries')
            ->assertOk()
            ->assertJsonPath('data.0.id', $inquiry->id)
            ->assertJsonPath('data.0.customer_name', 'Ana Anic')
            ->assertJsonPath('data.0.product_name', 'Laptop X')
            ->assertJsonPath('data.0.status_label', 'Nova');

        $this->getJsonStateful('/api/v1/seller/installment-inquiries?search=Laptop')
            ->assertOk()
            ->assertJsonPath('data.0.id', $inquiry->id);

        $this->getJsonStateful("/api/v1/seller/installment-inquiries/{$inquiry->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@test.test')
            ->assertJsonPath('data.installment_type_label', 'Mikrofin')
            ->assertJsonPath('data.months', 12);

        $this->patchJsonStateful("/api/v1/seller/installment-inquiries/{$inquiry->id}/status", [
            'status' => 'kontaktirana',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'kontaktirana')
            ->assertJsonPath('data.status_label', 'Kontaktirana');

        $this->assertDatabaseHas('installment_inquiries', [
            'id' => $inquiry->id,
            'status' => 'kontaktirana',
        ]);
    }

    public function test_guest_cannot_list_installment_inquiries(): void
    {
        $this->getJson('/api/v1/seller/installment-inquiries')
            ->assertUnauthorized();
    }

    public function test_notification_links_to_seller_portal(): void
    {
        $inquiry = $this->createInquiry();

        $html = (new InstallmentInquiryNotification($inquiry))->render();

        $this->assertStringContainsString(
            'https://bnc.ba/prodavac/kupovina-na-rate/'.$inquiry->id,
            $html,
        );
    }

    private function loginSeller(string $email): User
    {
        $role = Role::findOrCreate('Prodavac');

        $seller = User::createAccount([
            'name' => 'Test Prodavac',
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_customer' => false,
        ]);
        $seller->assignRole($role);

        $this->postJsonStateful('/api/v1/seller/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();

        return $seller;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInquiry(array $overrides = []): InstallmentInquiry
    {
        return InstallmentInquiry::query()->create(array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Anic',
            'phone' => '061111111',
            'email' => 'ana@test.test',
            'product_name' => 'Laptop X',
            'product_slug' => 'laptop-x',
            'quantity' => 1,
            'base_price' => 1200,
            'installment_type' => 'mikrofin',
            'months' => 12,
            'monthly_amount' => 110,
            'total_amount' => 1320,
            'interest_rate' => 0.05,
            'provision_rate' => 0.02,
            'status' => 'nova',
        ], $overrides));
    }
}
