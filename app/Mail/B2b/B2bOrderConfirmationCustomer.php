<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\B2bOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bOrderConfirmationCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesB2bMailIdentity;

    public function __construct(
        public B2bOrder $order,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
            subject: 'Potvrda B2B narudžbe '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.b2b.order-confirmation-customer',
        );
    }
}
