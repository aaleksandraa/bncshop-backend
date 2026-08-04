<?php

namespace App\Mail;

use App\Mail\Concerns\LogsMailableIdentity;
use App\Models\Order;
use App\Support\OrderDisplayLabels;
use App\Support\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, LogsMailableIdentity;

    private TemplatedOrderMail $templatedMail;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
    ) {
        $this->templatedMail = new TemplatedOrderMail(
            templateSlug: $this->templateSlugForStatus($order, $newStatus),
            order: $order,
            extraVariables: [
                'old_status' => OrderDisplayLabels::statusLabel($oldStatus, $order),
                'new_status' => OrderDisplayLabels::statusLabel($newStatus, $order),
            ],
            fallbackView: 'mail.order-status-changed',
            fallbackSubject: $this->fallbackSubject($order->order_number, $order, $newStatus),
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

    private function templateSlugForStatus(Order $order, string $status): string
    {
        if ($status === 'isporučeno' && OrderDisplayLabels::isPickup($order)) {
            return 'order_picked_up_customer';
        }

        return match ($status) {
            'poslano' => 'order_shipped_customer',
            'spremno_za_preuzimanje' => 'order_ready_for_pickup_customer',
            'otkazano' => 'order_cancelled_customer',
            'isporučeno' => 'order_completed_customer',
            default => 'order_shipped_customer',
        };
    }

    private function fallbackSubject(string $orderNumber, Order $order, string $status): string
    {
        if ($status === 'isporučeno' && OrderDisplayLabels::isPickup($order)) {
            return "Narudžba {$orderNumber} je preuzeta";
        }

        return match ($status) {
            'poslano' => "Vaša narudžba {$orderNumber} je poslana",
            'spremno_za_preuzimanje' => "Narudžba {$orderNumber} je spremna za preuzimanje",
            'otkazano' => "Vaša narudžba {$orderNumber} je otkazana",
            'isporučeno' => "Narudžba {$orderNumber} je uspješno isporučena",
            default => "Status narudžbe {$orderNumber}: ".OrderStatus::label($status),
        };
    }
}
