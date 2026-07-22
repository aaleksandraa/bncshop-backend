<?php

namespace Tests\Feature\B2b;

use App\Jobs\SendB2bNewProductDigestJob;
use App\Mail\B2b\B2bNewProductsDigestMail;
use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\B2bSetting;
use App\Models\User;
use App\Services\B2b\B2bNewProductNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class B2bNewProductNotificationTest extends TestCase
{
    use RefreshDatabase;

    private B2bCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->category = B2bCategory::query()->create([
            'name' => 'Laptopi',
            'slug' => 'laptopi',
            'is_active' => true,
        ]);
    }

    private function enableNotifications(): void
    {
        B2bSetting::query()->updateOrCreate([], [
            'default_customer_discount_percent' => 0,
            'notify_customers_on_new_product' => true,
        ]);

        Cache::forget('b2b_settings');
    }

    private function disableNotifications(): void
    {
        B2bSetting::query()->updateOrCreate([], [
            'default_customer_discount_percent' => 0,
            'notify_customers_on_new_product' => false,
        ]);

        Cache::forget('b2b_settings');
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

    public function test_does_not_dispatch_digest_job_when_toggle_is_off(): void
    {
        Queue::fake();
        $this->disableNotifications();

        $this->createProduct();

        Queue::assertNotPushed(SendB2bNewProductDigestJob::class);
    }

    public function test_dispatches_digest_job_and_queues_product_id_when_toggle_is_on(): void
    {
        Queue::fake();
        $this->enableNotifications();

        $product = $this->createProduct();

        Queue::assertPushed(SendB2bNewProductDigestJob::class);

        $ids = Cache::get(B2bNewProductNotificationService::DIGEST_CACHE_KEY, []);

        $this->assertContains($product->id, $ids);
    }

    public function test_does_not_notify_when_product_is_created_inactive(): void
    {
        Queue::fake();
        $this->enableNotifications();

        $this->createProduct(['is_active' => false]);

        Queue::assertNotPushed(SendB2bNewProductDigestJob::class);
    }

    public function test_notifies_when_inactive_product_is_activated(): void
    {
        Queue::fake();
        $this->enableNotifications();

        $product = $this->createProduct(['is_active' => false]);

        Queue::assertNotPushed(SendB2bNewProductDigestJob::class);

        $product->update(['is_active' => true]);

        Queue::assertPushed(SendB2bNewProductDigestJob::class);
    }

    public function test_does_not_notify_on_unrelated_product_update(): void
    {
        Queue::fake();
        $this->enableNotifications();

        $product = $this->createProduct();

        Queue::assertPushed(SendB2bNewProductDigestJob::class, 1);

        $product->update(['regular_price' => 250]);

        Queue::assertPushed(SendB2bNewProductDigestJob::class, 1);
    }

    public function test_digest_job_sends_one_mail_per_active_customer(): void
    {
        Mail::fake();
        Queue::fake();
        $this->enableNotifications();

        $activeOne = $this->createCustomer('active1@test.test');
        $activeTwo = $this->createCustomer('active2@test.test');
        $this->createCustomer('inactive@test.test', isActive: false);

        $productOne = $this->createProduct(['slug' => 'proizvod-a', 'name' => 'Proizvod A']);
        $productTwo = $this->createProduct(['slug' => 'proizvod-b', 'name' => 'Proizvod B']);

        Cache::put(
            B2bNewProductNotificationService::DIGEST_CACHE_KEY,
            [$productOne->id, $productTwo->id],
            now()->addHour(),
        );

        (new SendB2bNewProductDigestJob())->handle(app(B2bNewProductNotificationService::class));

        Mail::assertQueued(B2bNewProductsDigestMail::class, 2);

        Mail::assertQueued(B2bNewProductsDigestMail::class, function (B2bNewProductsDigestMail $mail) use ($activeOne): bool {
            return $mail->hasTo($activeOne->user->email);
        });

        Mail::assertQueued(B2bNewProductsDigestMail::class, function (B2bNewProductsDigestMail $class) use ($activeTwo): bool {
            return $class->hasTo($activeTwo->user->email);
        });

        Mail::assertNotQueued(B2bNewProductsDigestMail::class, function (B2bNewProductsDigestMail $mail): bool {
            return $mail->hasTo('inactive@test.test');
        });
    }

    public function test_digest_mail_is_plain_text_without_html(): void
    {
        $customer = $this->createCustomer('plain@test.test');
        $customer->load('user');
        $product = $this->createProduct(['name' => 'Plain proizvod', 'slug' => 'plain-proizvod']);

        $mail = new B2bNewProductsDigestMail(
            B2bProduct::query()->whereKey($product->id)->get(),
            $customer,
        );
        $rendered = strip_tags($mail->render());

        $this->assertStringContainsString('Plain proizvod', $rendered);
        $this->assertStringContainsString('/b2b/proizvod/plain-proizvod', $rendered);
        $this->assertStringNotContainsString('<html', strtolower($mail->render()));
    }

    public function test_digest_job_clears_pending_ids_when_toggle_is_off(): void
    {
        Mail::fake();
        $this->disableNotifications();

        $this->createCustomer('pending@test.test');
        Cache::put(B2bNewProductNotificationService::DIGEST_CACHE_KEY, [1, 2, 3], now()->addHour());

        (new SendB2bNewProductDigestJob())->handle(app(B2bNewProductNotificationService::class));

        Mail::assertNothingQueued();
        $this->assertSame([], Cache::get(B2bNewProductNotificationService::DIGEST_CACHE_KEY, []));
    }
}
