<?php

namespace App\Mail\B2b;

use App\Mail\B2b\Concerns\UsesB2bMailIdentity;
use App\Models\B2bOrder;
use App\Support\B2bOrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bOrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, UsesB2bMailIdentity;

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
            markdown: 'mail.b2b.order-status-changed',
        );
    }
}
