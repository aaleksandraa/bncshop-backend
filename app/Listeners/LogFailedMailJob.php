<?php

namespace App\Listeners;

use App\Services\Mail\EmailLogService;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogFailedMailJob
{
    public function __construct(
        private readonly EmailLogService $emailLogs,
    ) {}

    public function handle(JobFailed $event): void
    {
        try {
            $command = $this->resolveCommand($event);

            if (! $command instanceof SendQueuedMailable) {
                return;
            }

            $mailable = $command->mailable;
            $recipient = $this->resolveRecipient($mailable);
            $subject = null;

            try {
                if (method_exists($mailable, 'envelope')) {
                    $subject = $mailable->envelope()->subject;
                }
            } catch (Throwable) {
                $subject = null;
            }

            $templateSlug = is_object($mailable) && isset($mailable->templateSlug) && is_string($mailable->templateSlug)
                ? $mailable->templateSlug
                : null;

            $this->emailLogs->logFailed(
                recipient: $recipient !== '' ? $recipient : '(nepoznat)',
                errorMessage: $event->exception->getMessage(),
                subject: $subject,
                mailableClass: $mailable::class,
                templateSlug: $templateSlug,
                queued: true,
                context: [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'job_uuid' => method_exists($event->job, 'uuid') ? $event->job->uuid() : null,
                ],
            );
        } catch (Throwable $exception) {
            Log::error('Failed to log queued mail failure', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveCommand(JobFailed $event): mixed
    {
        $payload = $event->job->payload();
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command)) {
            return null;
        }

        return unserialize($command);
    }

    private function resolveRecipient(object $mailable): string
    {
        $to = data_get($mailable, 'to', []);

        if (! is_array($to) || $to === []) {
            return '';
        }

        return collect($to)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return $entry;
                }

                if (is_array($entry)) {
                    return $entry['address'] ?? $entry['email'] ?? null;
                }

                return data_get($entry, 'address') ?? data_get($entry, 'email');
            })
            ->filter()
            ->implode(', ');
    }
}
