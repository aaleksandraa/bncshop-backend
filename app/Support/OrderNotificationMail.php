<?php

namespace App\Support;

class OrderNotificationMail
{
    /**
     * Unique inboxes that receive B2C order alerts (new order, status updates, inquiries).
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        $emails = array_filter([
            config('bnc.seller_notification_email'),
            config('bnc.admin_notification_email'),
        ], fn (mixed $email): bool => is_string($email) && trim($email) !== '');

        $normalized = array_map(
            fn (string $email): string => strtolower(trim($email)),
            $emails,
        );

        return array_values(array_unique($normalized));
    }
}
