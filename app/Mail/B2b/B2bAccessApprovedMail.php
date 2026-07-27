<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bAccessApprovedMail extends Mailable
{
    use SerializesModels, UsesB2bMailIdentity, LogsMailableIdentity;

    public function __construct(
        public User $user,
        public string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
            subject: 'Vaš B2B pristup je odobren — postavite lozinku',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.b2b.access-approved-text',
        );
    }
}
