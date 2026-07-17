<?php

namespace App\Mail\B2b;

use App\Models\B2bAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bAccessRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public B2bAccessRequest $accessRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zahtjev za B2B pristup nije odobren',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.b2b.access-rejected',
        );
    }
}
