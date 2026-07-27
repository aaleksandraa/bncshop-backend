<?php

namespace App\Services\B2b;

use App\Mail\B2b\B2bAccessApprovedMail;
use App\Mail\B2b\B2bAccessRejectedMail;
use App\Mail\B2b\B2bAccessRequestNotification;
use App\Mail\B2b\B2bOrderConfirmationCustomer;
use App\Mail\B2b\B2bOrderNotificationAdmin;
use App\Mail\B2b\B2bOrderStatusChanged;
use App\Models\B2bAccessRequest;
use App\Models\B2bOrder;
use App\Models\B2bSetting;
use App\Models\User;
use App\Services\Mail\EmailLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class B2bAccessMailer
{
    public function __construct(
        private readonly EmailLogService $emailLogs,
    ) {}

    public function notifyAdminOfAccessRequest(B2bAccessRequest $request): void
    {
        $recipient = B2bSetting::adminNotificationEmail();

        if (! filled($recipient)) {
            $context = [
                'type' => 'access_request_admin',
                'request_id' => $request->id,
            ];

            Log::error('B2B access request mail skipped: admin notification email is empty', $context);

            $this->emailLogs->logSkipped(
                recipient: '',
                reason: 'Email za B2B obavijesti nije konfigurisan.',
                mailableClass: B2bAccessRequestNotification::class,
                context: $context,
            );

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

    public function sendOrderConfirmationCustomer(B2bOrder $order): void
    {
        $recipient = $order->contact_email;

        if (! filled($recipient)) {
            $context = [
                'type' => 'order_confirmation_customer',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ];

            Log::error('B2B order confirmation mail skipped: customer email is empty', $context);

            $this->emailLogs->logSkipped(
                recipient: '',
                reason: 'Email kupca je prazan.',
                mailableClass: B2bOrderConfirmationCustomer::class,
                context: $context,
            );

            return;
        }

        $this->send(
            recipient: $recipient,
            mailable: new B2bOrderConfirmationCustomer($order),
            context: [
                'type' => 'order_confirmation_customer',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );
    }

    public function sendOrderNotificationAdmin(B2bOrder $order): void
    {
        $recipient = B2bSetting::adminNotificationEmail();

        if (! filled($recipient)) {
            $context = [
                'type' => 'order_notification_admin',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ];

            Log::error('B2B order admin mail skipped: admin notification email is empty', $context);

            $this->emailLogs->logSkipped(
                recipient: '',
                reason: 'Email za B2B obavijesti nije konfigurisan.',
                mailableClass: B2bOrderNotificationAdmin::class,
                context: $context,
            );

            return;
        }

        $this->send(
            recipient: $recipient,
            mailable: new B2bOrderNotificationAdmin($order),
            context: [
                'type' => 'order_notification_admin',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );
    }

    public function sendOrderStatusChanged(B2bOrder $order, string $previousStatus): void
    {
        $recipient = $order->contact_email;

        if (! filled($recipient)) {
            $context = [
                'type' => 'order_status_changed_customer',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'previous_status' => $previousStatus,
                'new_status' => $order->status,
            ];

            Log::error('B2B order status mail skipped: customer email is empty', $context);

            $this->emailLogs->logSkipped(
                recipient: '',
                reason: 'Email kupca je prazan.',
                mailableClass: B2bOrderStatusChanged::class,
                context: $context,
            );

            return;
        }

        $this->send(
            recipient: $recipient,
            mailable: new B2bOrderStatusChanged($order, $previousStatus),
            context: [
                'type' => 'order_status_changed_customer',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'previous_status' => $previousStatus,
                'new_status' => $order->status,
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

        $subject = null;

        try {
            if (method_exists($mailable, 'envelope')) {
                $subject = $mailable->envelope()->subject;
            }
        } catch (\Throwable) {
            $subject = null;
        }

        try {
            EmailLogService::suppressNextMessageSent();
            Mail::to($recipient)->send($mailable);

            $this->emailLogs->logSent(
                recipient: $recipient,
                subject: $subject,
                mailableClass: $mailable::class,
                mailer: config('mail.default'),
                context: $context + [
                    'from' => config('mail.from.address'),
                    'reply_to' => config('b2b.mail.from_address'),
                ],
            );

            Log::info('B2B mail sent', $context + [
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
                'reply_to' => config('b2b.mail.from_address'),
            ]);
        } catch (\Throwable $exception) {
            $this->emailLogs->logFailed(
                recipient: $recipient,
                errorMessage: $exception->getMessage(),
                subject: $subject,
                mailableClass: $mailable::class,
                mailer: config('mail.default'),
                context: $context,
            );

            Log::error('B2B mail failed', $context + [
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Slanje emaila nije uspjelo. Provjerite mail postavke na serveru (MAIL_MAILER, sendmail, From adresa).',
                previous: $exception,
            );
        } finally {
            EmailLogService::clearMessageSentSuppression();
        }
    }
}
