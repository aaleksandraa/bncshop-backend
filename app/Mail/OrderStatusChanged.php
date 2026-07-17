<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private TemplatedOrderMail $templatedMail;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
    ) {
        $this->templatedMail = new TemplatedOrderMail(
            templateSlug: $this->templateSlugForStatus($newStatus),
            order: $order,
            extraVariables: [
                'old_status' => OrderStatus::label($oldStatus),
                'new_status' => OrderStatus::label($newStatus),
            ],
            fallbackView: 'mail.order-status-changed',
            fallbackSubject: $this->fallbackSubject($order->order_number, $newStatus),
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

    private function templateSlugForStatus(string $status): string
    {
        return match ($status) {
            'poslano' => 'order_shipped_customer',
            'otkazano' => 'order_cancelled_customer',
            'isporučeno' => 'order_completed_customer',
            default => 'order_shipped_customer',
        };
    }

    private function fallbackSubject(string $orderNumber, string $status): string
    {
        return match ($status) {
            'poslano' => "Vaša narudžba {$orderNumber} je poslana",
            'otkazano' => "Vaša narudžba {$orderNumber} je otkazana",
            'isporučeno' => "Narudžba {$orderNumber} je uspješno isporučena",
            default => "Status narudžbe {$orderNumber}: ".OrderStatus::label($status),
        };
    }
}
