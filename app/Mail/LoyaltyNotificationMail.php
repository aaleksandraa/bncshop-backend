<?php

namespace App\Mail;

use App\Mail\Concerns\LogsMailableIdentity;
use App\Services\Mail\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoyaltyNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, LogsMailableIdentity;

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public string $templateSlug,
        public array $variables,
        public string $fallbackSubject,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = app(EmailTemplateRenderer::class)->render($this->templateSlug, $this->variables);

        return new Envelope(
            subject: $rendered['subject'] ?? $this->fallbackSubject,
        );
    }

    public function content(): Content
    {
        $rendered = app(EmailTemplateRenderer::class)->render($this->templateSlug, $this->variables);

        if ($rendered !== null) {
            return new Content(htmlString: $rendered['body']);
        }

        return new Content(
            htmlString: '<p>'.$this->fallbackSubject.'</p>',
        );
    }
}
