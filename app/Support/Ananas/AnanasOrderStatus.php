<?php

namespace App\Support\Ananas;

class AnanasOrderStatus
{
    public const BUCKET_AWAITING = 'awaiting_confirm';

    public const BUCKET_PROCESSING = 'processing';

    public const BUCKET_ON_DELIVERY = 'on_delivery';

    public const BUCKET_COMPLETED = 'completed';

    public const BUCKET_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const AWAITING = [
        'CREATED',
    ];

    /** @var list<string> */
    public const PROCESSING = [
        'PENDING',
        'NOT_READY',
        'PARTIALLY_CONFIRMED',
        'CONFIRMED',
        'PACKING_INITIATED',
        'PARTIALLY_PACKED',
        'PACKED',
        'READY_FOR_COLLECTION',
    ];

    /** @var list<string> */
    public const ON_DELIVERY = [
        'COLLECTED',
        'ON_DELIVERY',
        'ATTEMPTED_DELIVERY',
        'REDIRECTED_DELIVERY',
        'DELAYED_DELIVERY',
        'COLLECTED_PUDO',
        'ON_DELIVERY_PUDO',
        'DELIVERED_BY_CARRIER_PUDO',
        'DELIVERED_PUDO',
        'ATTEMPTED_DELIVERY_PUDO',
        'COLLECTED_LOCKER',
        'ON_DELIVERY_LOCKER',
        'DELIVERED_LOCKER',
    ];

    /** @var list<string> */
    public const COMPLETED = [
        'DELIVERED',
        'COMPLETED_PUDO',
        'COMPLETED_LOCKER',
    ];

    /** @var list<string> */
    public const CANCELLED = [
        'CANCELLED',
        'CANCELLED_BY_JOB',
        'CANCELLED_BY_CUSTOMER',
        'CANCELLED_BY_MERCHANT',
        'CANCELLED_BY_EMPLOYEE',
        'TERMINATED',
        'MISSING_ITEMS',
        'REJECTED_DELIVERY',
        'CUSTOMER_ADDRESS_INVALID',
        'RETURN_TO_SELLER',
        'RETURN_TO_SELLER_UNVERIFIED',
        'RETURN_TO_SELLER_CANCELLED',
        'RETURNED_TO_SELLER',
        'DAMAGED',
        'DAMAGED_UNVERIFIED',
        'DAMAGED_CANCELLED',
        'LOST',
        'LOST_UNVERIFIED',
        'LOST_CANCELLED',
        'REJECTED_DELIVERY_PUDO',
        'ADDRESS_INVALID_PUDO',
        'DAMAGED_AT_PUDO',
        'DAMAGED_AT_PUDO_UNVERIFIED',
        'DAMAGED_AT_PUDO_CANCELLED',
        'LOST_AT_PUDO',
        'LOST_AT_PUDO_UNVERIFIED',
        'LOST_AT_PUDO_CANCELLED',
    ];

    public static function bucket(?string $remoteStatus, bool $awaitingConfirmation = false): string
    {
        $status = strtoupper(trim((string) $remoteStatus));

        if ($status === '' && $awaitingConfirmation) {
            return self::BUCKET_AWAITING;
        }

        if (in_array($status, self::AWAITING, true) || ($awaitingConfirmation && $status === '')) {
            return self::BUCKET_AWAITING;
        }

        if (in_array($status, self::COMPLETED, true)) {
            return self::BUCKET_COMPLETED;
        }

        if (in_array($status, self::CANCELLED, true)) {
            return self::BUCKET_CANCELLED;
        }

        if (in_array($status, self::ON_DELIVERY, true)) {
            return self::BUCKET_ON_DELIVERY;
        }

        if (in_array($status, self::PROCESSING, true)) {
            return self::BUCKET_PROCESSING;
        }

        return $status !== '' ? self::BUCKET_PROCESSING : self::BUCKET_AWAITING;
    }

    public static function isTerminal(?string $remoteStatus): bool
    {
        $status = strtoupper(trim((string) $remoteStatus));

        return in_array($status, array_merge(self::COMPLETED, self::CANCELLED), true);
    }

    public static function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            self::BUCKET_AWAITING => 'Čeka potvrdu',
            self::BUCKET_PROCESSING => 'U obradi',
            self::BUCKET_ON_DELIVERY => 'Na dostavi',
            self::BUCKET_COMPLETED => 'Završeno',
            self::BUCKET_CANCELLED => 'Otkazano / nije isporučeno',
            default => $bucket,
        };
    }

    /**
     * @param  list<string>  $paymentMethods
     */
    public static function paymentLabel(array $paymentMethods): string
    {
        $labels = array_map(static function (string $code): string {
            return match (strtoupper($code)) {
                'COD' => 'Pouzeće',
                'PBC' => 'Kartica',
                'POS' => 'Na mjestu',
                'VOUCHER' => 'Vaučer',
                default => $code,
            };
        }, $paymentMethods);

        return $labels !== [] ? implode(', ', $labels) : '—';
    }
}
