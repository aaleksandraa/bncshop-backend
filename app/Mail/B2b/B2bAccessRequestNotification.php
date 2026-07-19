<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\B2bAccessRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bAccessRequestNotification extends Mailable
{
    use SerializesModels, UsesB2bMailIdentity;

    public function __construct(
        public B2bAccessRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
            subject: 'Novi zahtjev za B2B pristup — '.$this->request->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.b2b.access-request-notification-text',
        );
    }
}
