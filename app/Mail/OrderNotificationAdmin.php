<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNotificationAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private TemplatedOrderMail $templatedMail;

    public function __construct(
        public Order $order,
    ) {
        $this->templatedMail = new TemplatedOrderMail(
            templateSlug: 'order_notification_seller',
            order: $order,
            fallbackView: 'mail.order-notification-admin',
            fallbackSubject: 'Nova narudžba '.$order->order_number,
        );
    }

    public function envelope(): Envelope
    {
        return $this->templatedMail->envelope();
    }

    public function content(): Content
    {
        return $this->templatedMail->content();
    }
}
