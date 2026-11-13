<?php

namespace App\Mail;

use App\Models\AnanasOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnanasOrderNotificationAdmin extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AnanasOrder $order,
    ) {
        $this->order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Nova Ananas narudžba %s — %s',
                $this->order->ananas_order_id,
                $this->order->customerName(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ananas-order-notification-admin',
        );
    }
}
