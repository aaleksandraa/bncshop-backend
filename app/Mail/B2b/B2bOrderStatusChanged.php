<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\B2bOrder;
use App\Support\B2bOrderStatus;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bOrderStatusChanged extends Mailable
{
    use SerializesModels, UsesB2bMailIdentity, LogsMailableIdentity;

    public function __construct(
        public B2bOrder $order,
        public string $previousStatus,
    ) {}

    public function envelope(): Envelope
    {
        return $this->b2bEnvelope(
            subject: 'Status B2B narudžbe '.$this->order->order_number.' — '.B2bOrderStatus::label($this->order->status),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.b2b.order-status-changed-text',
        );
    }
}
