<?php

namespace App\Services\B2b;

use App\Mail\B2b\B2bAccessApprovedMail;
use App\Mail\B2b\B2bAccessRejectedMail;
use App\Mail\B2b\B2bAccessRequestNotification;
use App\Models\B2bAccessRequest;
use App\Models\B2bSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class B2bAccessMailer
{
    public function notifyAdminOfAccessRequest(B2bAccessRequest $request): void
    {
        $recipient = B2bSetting::adminNotificationEmail();

        if (! filled($recipient)) {
            Log::error('B2B access request mail skipped: admin notification email is empty', [
                'request_id' => $request->id,
            ]);

            throw new RuntimeException('Email za B2B obavijesti nije konfigurisan.');
        }

        $this->send(
            recipient: $recipient,
            mailable: new B2bAccessRequestNotification($request),
            context: [
                'type' => 'access_request_admin',
                'request_id' => $request->id,
            ],
        );
    }

    public function sendAccessApproved(User $user, string $setupUrl): void
    {
        $this->send(
            recipient: $user->email,
            mailable: new B2bAccessApprovedMail($user, $setupUrl),
            context: [
                'type' => 'access_approved_customer',
                'user_id' => $user->id,
            ],
        );
    }

    public function sendAccessRejected(B2bAccessRequest $request): void
    {
        $this->send(
            recipient: $request->email,
            mailable: new B2bAccessRejectedMail($request),
            context: [
                'type' => 'access_rejected_customer',
                'request_id' => $request->id,
            ],
        );
    }

    private function send(string $recipient, object $mailable, array $context): void
    {
        if (config('mail.default') === 'log') {
            Log::warning('B2B mail is using the log mailer; no real email will be delivered', $context + [
                'recipient' => $recipient,
            ]);
        }

        try {
            Mail::to($recipient)->send($mailable);

            Log::info('B2B access mail sent', $context + [
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
                'reply_to' => config('b2b.mail.from_address'),
            ]);
        } catch (\Throwable $exception) {
            Log::error('B2B access mail failed', $context + [
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Slanje emaila nije uspjelo. Provjerite mail postavke na serveru (MAIL_MAILER, sendmail, From adresa).',
                previous: $exception,
            );
        }
    }
}
