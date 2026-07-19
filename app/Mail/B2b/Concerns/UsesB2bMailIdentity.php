<?php

namespace App\Mail\B2b\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait UsesB2bMailIdentity
{
    protected function b2bMailFrom(): Address
    {
        return new Address(
            (string) config('b2b.mail.from_address'),
            (string) config('b2b.mail.from_name'),
        );
    }

    protected function b2bEnvelope(string $subject): Envelope
    {
        $from = $this->b2bMailFrom();

        return new Envelope(
            from: $from,
            replyTo: [$from],
            subject: $subject,
        );
    }
}
