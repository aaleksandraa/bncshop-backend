<?php

namespace App\Mail\B2b;

use App\Models\B2bAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bAccessRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public B2bAccessRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novi zahtjev za B2B pristup — '.$this->request->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.b2b.access-request-notification',
        );
    }
}
