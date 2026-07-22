<?php

namespace Tests\Feature\B2b;

use App\Mail\B2b\B2bAccessApprovedMail;
use App\Mail\B2b\B2bAccessRequestNotification;
use App\Models\B2bAccessRequest;
use App\Models\B2bSetting;
use App\Services\B2b\B2bCustomerProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class B2bAccessRequestMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function accessRequestPayload(string $suffix = '1'): array
    {
        return [
            'first_name' => 'Amir',
            'last_name' => 'Hadžić',
            'phone' => '061234567',
            'email' => "firma{$suffix}@test.test",
            'company_name' => "Test d.o.o. {$suffix}",
            'company_address' => 'Ulica 1, Sarajevo',
            'jib' => str_pad((string) (1000000000000 + (int) $suffix), 13, '0', STR_PAD_LEFT),
            'pdv_number' => '1234567890123',
        ];
    }

    public function test_access_request_sends_admin_notification_immediately(): void
    {
        Mail::fake();

        config([
            'b2b.mail.admin_notification_email' => 'b2b@bncshop.ba',
            'turnstile.enabled' => false,
        ]);

        B2bSetting::query()->delete();

        $this->postJson('/api/v1/b2b/access-request', $this->accessRequestPayload())
            ->assertCreated();

        Mail::assertSent(B2bAccessRequestNotification::class, function (B2bAccessRequestNotification $mail): bool {
            return $mail->hasTo('b2b@bncshop.ba')
                && $mail->request->company_name === 'Test d.o.o. 1';
        });

        Mail::assertNotQueued(B2bAccessRequestNotification::class);
    }

    public function test_approving_access_request_sends_customer_email_immediately(): void
    {
        Mail::fake();

        config([
            'bnc.frontend_url' => 'https://bncshop.ba',
        ]);

        $request = B2bAccessRequest::query()->create(
            $this->accessRequestPayload('2') + ['status' => 'pending'],
        );

        $customer = app(B2bCustomerProvisioner::class)->approveAccessRequest($request);

        Mail::assertSent(B2bAccessApprovedMail::class, function (B2bAccessApprovedMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->user->email)
                && str_contains($mail->setupUrl, 'https://bncshop.ba/b2b/postavi-lozinku?token=');
        });

        Mail::assertNotQueued(B2bAccessApprovedMail::class);
    }

    public function test_admin_can_create_customer_directly(): void
    {
        Mail::fake();

        config([
            'bnc.frontend_url' => 'https://bncshop.ba',
        ]);

        $payload = $this->accessRequestPayload('3');

        $customer = app(B2bCustomerProvisioner::class)->createCustomer([
            'name' => $payload['first_name'].' '.$payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'company_name' => $payload['company_name'],
            'company_address' => $payload['company_address'],
            'jib' => $payload['jib'],
            'pdv_number' => $payload['pdv_number'],
        ]);

        $this->assertSame($payload['company_name'], $customer->company_name);
        $this->assertSame($payload['email'], $customer->user->email);
        $this->assertTrue($customer->user->is_b2b_customer);

        Mail::assertSent(B2bAccessApprovedMail::class, function (B2bAccessApprovedMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->user->email)
                && str_contains($mail->setupUrl, 'https://bncshop.ba/b2b/postavi-lozinku?token=');
        });
    }
}
