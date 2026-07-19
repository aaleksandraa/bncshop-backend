<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\B2bAccessRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bAccessRejectedMail extends Mailable
{
    use SerializesModels, UsesB2bMailIdentity;

    public function __construct(
        public B2bAccessRequest $accessRequest,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
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
