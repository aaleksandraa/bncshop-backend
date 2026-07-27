<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailLogService
{
    private static bool $suppressNextMessageSent = false;

    public static function suppressNextMessageSent(): void
    {
        self::$suppressNextMessageSent = true;
    }

    public static function clearMessageSentSuppression(): void
    {
        self::$suppressNextMessageSent = false;
    }

    public static function shouldSuppressMessageSent(): bool
    {
        if (! self::$suppressNextMessageSent) {
            return false;
        }

        self::$suppressNextMessageSent = false;

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logSent(
        string $recipient,
        ?string $subject = null,
        ?string $mailableClass = null,
        ?string $templateSlug = null,
        string $channel = EmailLog::CHANNEL_LARAVEL,
        ?string $mailer = null,
        bool $queued = false,
        array $context = [],
    ): EmailLog {
        return $this->write([
            'channel' => $channel,
            'status' => EmailLog::STATUS_SENT,
            'recipient' => $recipient,
            'subject' => $subject,
            'mailable_class' => $mailableClass,
            'template_slug' => $templateSlug,
            'mailer' => $mailer ?? config('mail.default'),
            'queued' => $queued,
            'context' => $context ?: null,
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logFailed(
        string $recipient,
        string $errorMessage,
        ?string $subject = null,
        ?string $mailableClass = null,
        ?string $templateSlug = null,
        string $channel = EmailLog::CHANNEL_LARAVEL,
        ?string $mailer = null,
        bool $queued = false,
        array $context = [],
    ): EmailLog {
        return $this->write([
            'channel' => $channel,
            'status' => EmailLog::STATUS_FAILED,
            'recipient' => $recipient,
            'subject' => $subject,
            'mailable_class' => $mailableClass,
            'template_slug' => $templateSlug,
            'mailer' => $mailer ?? config('mail.default'),
            'queued' => $queued,
            'context' => $context ?: null,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logSkipped(
        string $recipient,
        string $reason,
        ?string $mailableClass = null,
        string $channel = EmailLog::CHANNEL_LARAVEL,
        array $context = [],
    ): EmailLog {
        return $this->write([
            'channel' => $channel,
            'status' => EmailLog::STATUS_SKIPPED,
            'recipient' => $recipient !== '' ? $recipient : '(prazan)',
            'subject' => null,
            'mailable_class' => $mailableClass,
            'template_slug' => null,
            'mailer' => config('mail.default'),
            'queued' => false,
            'context' => $context ?: null,
            'error_message' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): EmailLog
    {
        try {
            return EmailLog::query()->create($attributes);
        } catch (Throwable $exception) {
            Log::error('Failed to persist email log entry', [
                'error' => $exception->getMessage(),
                'attributes' => [
                    'status' => $attributes['status'] ?? null,
                    'recipient' => $attributes['recipient'] ?? null,
                    'mailable_class' => $attributes['mailable_class'] ?? null,
                ],
            ]);

            return new EmailLog($attributes);
        }
    }
}
