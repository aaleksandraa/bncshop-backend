<?php

namespace App\Mail;

use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, LogsMailableIdentity;

    private TemplatedOrderMail $templatedMail;

    public function __construct(
        public Order $order,
    ) {
        $this->templatedMail = new TemplatedOrderMail(
            templateSlug: 'order_confirmation_customer',
            order: $order,
            fallbackView: 'mail.order-confirmation-customer',
            fallbackSubject: 'Potvrda narudžbe '.$order->order_number,
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
