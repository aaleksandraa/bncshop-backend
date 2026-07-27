<?php

namespace App\Listeners;

use App\Services\Mail\EmailLogService;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LogSentMail
{
    public function __construct(
        private readonly EmailLogService $emailLogs,
    ) {}

    public function handle(MessageSent $event): void
    {
        if (EmailLogService::shouldSuppressMessageSent()) {
            return;
        }

        $email = $event->message;

        if (! $email instanceof Email) {
            return;
        }

        $recipient = $this->formatAddresses($email->getTo());

        if ($recipient === '') {
            return;
        }

        $mailableClass = $this->resolveMailableClass($event, $email);

        $this->emailLogs->logSent(
            recipient: $recipient,
            subject: $email->getSubject(),
            mailableClass: $mailableClass,
            templateSlug: $this->headerValue($email, 'X-Template-Slug'),
            mailer: config('mail.default'),
            queued: false,
            context: array_filter([
                'from' => $this->formatAddresses($email->getFrom()),
                'reply_to' => $this->formatAddresses($email->getReplyTo()),
            ], fn ($value) => filled($value)),
        );
    }

    private function resolveMailableClass(MessageSent $event, Email $email): ?string
    {
        $fromHeader = $this->headerValue($email, 'X-Mailable');

        if (filled($fromHeader)) {
            return $fromHeader;
        }

        $data = $event->data ?? [];

        if (isset($data['__laravel_mailable']) && is_string($data['__laravel_mailable'])) {
            return $data['__laravel_mailable'];
        }

        return null;
    }

    private function headerValue(Email $email, string $name): ?string
    {
        $headers = $email->getHeaders();

        if (! $headers->has($name)) {
            return null;
        }

        return $headers->get($name)?->getBodyAsString();
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private function formatAddresses(array $addresses): string
    {
        return collect($addresses)
            ->map(fn (Address $address) => $address->getAddress())
            ->filter()
            ->implode(', ');
    }
}
