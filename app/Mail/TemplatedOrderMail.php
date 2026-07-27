<?php

namespace App\Mail;

use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\Order;
use App\Services\Mail\EmailTemplateRenderer;
use App\Services\Mail\OrderEmailVariables;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, LogsMailableIdentity;

    /**
     * @param  array<string, string>  $extraVariables
     */
    public function __construct(
        public string $templateSlug,
        public Order $order,
        public array $extraVariables = [],
        public ?string $fallbackView = null,
        public ?string $fallbackSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = $this->renderTemplate();
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'BNC Shop');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($fromAddress, $fromName)],
            subject: $rendered['subject'] ?? $this->fallbackSubject ?? ('Narudžba '.$this->order->order_number),
        );
    }

    public function content(): Content
    {
        $rendered = $this->renderTemplate();

        if ($rendered !== null) {
            return new Content(htmlString: $rendered['body']);
        }

        if ($this->fallbackView !== null) {
            return new Content(
                view: $this->fallbackView,
                with: [
                    'order' => $this->order->loadMissing('items'),
                    'currency' => config('bnc.currency_symbol'),
                    ...$this->extraVariables,
                ],
            );
        }

        return new Content(
            htmlString: '<p>Narudžba '.$this->order->order_number.'</p>',
        );
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private function renderTemplate(): ?array
    {
        return app(EmailTemplateRenderer::class)->render(
            $this->templateSlug,
            OrderEmailVariables::from($this->order, $this->extraVariables),
        );
    }
}
