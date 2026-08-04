<?php

namespace App\Support;

use App\Models\Order;

class OrderDisplayLabels
{
    public static function isPickup(Order|string|null $orderOrShippingMethod): bool
    {
        if ($orderOrShippingMethod instanceof Order) {
            return (string) $orderOrShippingMethod->shipping_method === 'pickup';
        }

        return (string) $orderOrShippingMethod === 'pickup';
    }

    public static function shippingMethodLabel(?string $method): string
    {
        return match ((string) $method) {
            'delivery' => 'Dostava na adresu',
            'pickup' => 'Preuzimanje u poslovnici',
            default => $method !== null && $method !== '' ? $method : '—',
        };
    }

    public static function paymentMethodLabel(?string $method, ?string $shippingMethod = null): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        if (self::isPickup($shippingMethod) && in_array($method, ['cod', 'pay_on_delivery'], true)) {
            return 'Plaćanje pri preuzimanju u poslovnici';
        }

        return match ($method) {
            'cod', 'pay_on_delivery' => 'Plaćanje pouzećem',
            'card' => 'Kartica',
            'bank_transfer' => 'Virman',
            default => $method,
        };
    }

    public static function paymentMethodLabelForOrder(Order $order): string
    {
        return self::paymentMethodLabel((string) $order->payment_method, (string) $order->shipping_method);
    }

    public static function shippingFeeDisplay(Order $order): string
    {
        $currency = (string) config('bnc.currency_symbol', 'KM');

        if (self::isPickup($order)) {
            return 'Besplatno (preuzimanje u poslovnici)';
        }

        return number_format((float) $order->shipping_fee, 2, ',', '.').' '.$currency;
    }

    public static function shippingSummaryLabel(Order $order): string
    {
        return self::isPickup($order) ? 'Način preuzimanja' : 'Dostava';
    }

    public static function statusLabel(string $status, ?Order $order = null): string
    {
        if ($order !== null && self::isPickup($order) && $status === 'isporučeno') {
            return 'Preuzeto';
        }

        return OrderStatus::label($status);
    }
}
