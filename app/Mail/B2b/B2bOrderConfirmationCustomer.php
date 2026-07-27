<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\B2bOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bOrderConfirmationCustomer extends Mailable
{
    use SerializesModels, UsesB2bMailIdentity, LogsMailableIdentity;

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
            text: 'mail.b2b.order-confirmation-customer-text',
        );
    }
}
