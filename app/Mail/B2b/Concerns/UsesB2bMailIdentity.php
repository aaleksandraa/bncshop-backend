<?php

namespace App\Mail\B2b\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait UsesB2bMailIdentity
{
    protected function b2bBrandAddress(): Address
    {
        $address = config('b2b.mail.from_address') ?: config('mail.from.address');
        $name = config('b2b.mail.from_name', 'BNC B2B');

        return new Address((string) $address, (string) $name);
    }

    protected function b2bTransportFrom(): Address
    {
        if (config('b2b.mail.use_global_from', true)) {
            return new Address(
                (string) config('mail.from.address'),
                (string) (config('b2b.mail.from_name') ?: config('mail.from.name', 'BNC B2B')),
            );
        }

        return $this->b2bBrandAddress();
    }

    protected function b2bEnvelope(string $subject): Envelope
    {
        $from = $this->b2bTransportFrom();

        $replyTo = config('b2b.mail.use_global_from', true)
            ? [$from]
            : [$this->b2bBrandAddress()];

        return new Envelope(
            from: $from,
            replyTo: $replyTo,
            subject: $subject,
        );
    }
}
