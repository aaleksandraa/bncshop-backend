<?php

namespace Tests\Feature\B2b;

use App\Filament\B2b\Pages\SendProductNotificationPage;
use App\Jobs\SendB2bManualProductNotificationJob;
use App\Mail\B2b\B2bNewProductsDigestMail;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\User;
use App\Services\B2b\B2bManualProductNotificationService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bManualProductNotificationTest extends TestCase
{
    use RefreshDatabase;

    private B2bCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);
    }

    private function createCustomer(string $email, bool $isActive = true): B2bCustomer
    {
        $user = User::createAccount([
            'name' => 'Kupac '.$email,
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_b2b_customer' => true,
        ]);

        return B2bCustomer::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Firma '.$email,
            'company_address' => 'Adresa 1',
            'jib' => str_replace(['@', '.'], '', $email).random_int(100, 999),
            'phone' => '061111111',
            'is_active' => $isActive,
        ]);
    }

    private function createProduct(array $overrides = []): B2bProduct
    {
        return B2bProduct::query()->create(array_merge([
            'b2b_category_id' => $this->category->id,
            'name' => 'Novi proizvod',
            'slug' => 'novi-proizvod-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'regular_price' => 199.99,
            'stock_quantity' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_service_dispatches_manual_notification_job(): void
    {
        Queue::fake();

        $product = $this->createProduct();
        $this->createCustomer('active@test.test');

        $recipientCount = app(B2bManualProductNotificationService::class)->send(
            [$product->id],
            'Posebna ponuda za naše partnere.',
        );

        $this->assertSame(1, $recipientCount);

        Queue::assertPushed(SendB2bManualProductNotificationJob::class, function (SendB2bManualProductNotificationJob $job) use ($product): bool {
            return $job->productIds === [$product->id]
                && $job->customIntro === 'Posebna ponuda za naše partnere.';
        });
    }

    public function test_manual_notification_job_sends_one_mail_per_active_customer(): void
    {
        Mail::fake();

        $activeOne = $this->createCustomer('active1@test.test');
        $activeTwo = $this->createCustomer('active2@test.test');
        $this->createCustomer('inactive@test.test', isActive: false);

        $productOne = $this->createProduct(['slug' => 'proizvod-a', 'name' => 'Proizvod A']);
        $productTwo = $this->createProduct(['slug' => 'proizvod-b', 'name' => 'Proizvod B']);

        (new SendB2bManualProductNotificationJob(
            [$productOne->id, $productTwo->id],
            'Dodatni uvodni tekst.',
        ))->handle(app(B2bManualProductNotificationService::class));

        Mail::assertQueued(B2bNewProductsDigestMail::class, 2);

        Mail::assertQueued(B2bNewProductsDigestMail::class, function (B2bNewProductsDigestMail $mail) use ($activeOne): bool {
            return $mail->hasTo($activeOne->user->email)
                && $mail->customIntro === 'Dodatni uvodni tekst.';
        });

        Mail::assertNotQueued(B2bNewProductsDigestMail::class, function (B2bNewProductsDigestMail $mail): bool {
            return $mail->hasTo('inactive@test.test');
        });
    }

    public function test_manual_notification_mail_includes_custom_and_predefined_intro(): void
    {
        $customer = $this->createCustomer('plain@test.test');
        $customer->load('user');
        $product = $this->createProduct(['name' => 'Plain proizvod', 'slug' => 'plain-proizvod']);

        $mail = new B2bNewProductsDigestMail(
            B2bProduct::query()->whereKey($product->id)->get(),
            $customer,
            'Poseban tekst prije liste.',
        );
        $rendered = $mail->render();

        $this->assertStringContainsString('Poseban tekst prije liste.', $rendered);
        $this->assertStringContainsString('U B2B katalog su dodani novi proizvodi:', $rendered);
        $this->assertStringContainsString('Plain proizvod', $rendered);
        $this->assertStringContainsString('/b2b/proizvod/plain-proizvod', $rendered);
    }

    public function test_manual_notification_mail_preserves_quotes_in_product_names(): void
    {
        $customer = $this->createCustomer('quotes@test.test');
        $customer->load('user');
        $product = $this->createProduct([
            'name' => 'Dell P2422H 24"',
            'slug' => 'dell-p2422h-24',
        ]);

        $mail = new B2bNewProductsDigestMail(
            B2bProduct::query()->whereKey($product->id)->get(),
            $customer,
        );
        $rendered = $mail->render();

        $this->assertStringContainsString('Dell P2422H 24"', $rendered);
        $this->assertStringNotContainsString('&quot;', $rendered);
        $this->assertStringContainsString('/b2b/proizvod/dell-p2422h-24', $rendered);
    }

    public function test_service_rejects_inactive_products_only(): void
    {
        Queue::fake();

        $inactiveProduct = $this->createProduct(['is_active' => false]);
        $this->createCustomer('active@test.test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Odaberite barem jedan aktivan proizvod.');

        app(B2bManualProductNotificationService::class)->send([$inactiveProduct->id]);
    }

    public function test_selection_windows_include_only_matching_active_products(): void
    {
        Carbon::setTestNow('2026-10-20 15:00:00');

        $today = $this->createProduct(['name' => 'Danas']);
        $yesterday = $this->createProduct(['name' => 'Jucer']);
        $older = $this->createProduct(['name' => 'Stariji']);
        $inactiveToday = $this->createProduct(['name' => 'Neaktivan', 'is_active' => false]);

        $yesterday->forceFill(['created_at' => now()->subDay()->setTime(11, 0)])->save();
        $older->forceFill(['created_at' => now()->subDays(10)])->save();
        $inactiveToday->forceFill(['created_at' => now()])->save();

        $service = app(B2bManualProductNotificationService::class);

        $this->assertSame(
            [$today->id],
            $service->activeProductIdsCreatedBetween(now()->startOfDay())['ids'],
        );
        $this->assertSame(
            [$yesterday->id],
            $service->activeProductIdsCreatedBetween(now()->subDay()->startOfDay(), now()->startOfDay())['ids'],
        );
        $this->assertEqualsCanonicalizing(
            [$today->id, $yesterday->id],
            $service->activeProductIdsCreatedBetween(now()->startOfWeek(Carbon::MONDAY))['ids'],
        );
        $this->assertNotContains(
            $older->id,
            $service->activeProductIdsCreatedBetween(now()->subDays(7))['ids'],
        );

        Carbon::setTestNow();
    }

    public function test_notification_page_shortcuts_replace_and_clear_selection(): void
    {
        Carbon::setTestNow('2026-10-20 15:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::createAccount([
            'name' => 'B2B Admin',
            'email' => 'b2b-notify-admin@test.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole(Role::findByName('B2B Admin'));

        $today = $this->createProduct(['name' => 'Danas']);
        $older = $this->createProduct(['name' => 'Stariji']);
        $older->forceFill(['created_at' => now()->subDays(10)])->save();

        Livewire::actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('b2b-admin'));
        Filament::bootCurrentPanel();

        Livewire::test(SendProductNotificationPage::class)
            ->call('selectAddedToday')
            ->assertSet('data.product_ids', [$today->id])
            ->call('clearSelectedProducts')
            ->assertSet('data.product_ids', [])
            ->assertSet('data.custom_intro', (string) config('b2b.product_notification.default_custom_intro'));

        $this->assertDatabaseHas('b2b_products', ['id' => $today->id]);
        $this->assertDatabaseHas('b2b_products', ['id' => $older->id]);

        Carbon::setTestNow();
    }
}
