<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bPasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesB2bMailIdentity;

    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
            subject: 'Resetovanje B2B lozinke',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.b2b.password-reset',
        );
    }
}
