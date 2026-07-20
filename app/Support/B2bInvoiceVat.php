<?php

namespace App\Support;

use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\SystemSetting;

class B2bInvoiceVat
{
    public static function ratePercent(): float
    {
        return (float) config('b2b.vat_rate_percent', 17);
    }

    public static function rate(): float
    {
        return self::ratePercent() / 100;
    }

    /**
     * @return array{
     *     name: string,
     *     address: string,
     *     phone: string,
     *     email: string,
     *     jib: ?string,
     *     pdv_number: ?string,
     * }
     */
    public static function sellerDetails(): array
    {
        $shopName = SystemSetting::query()->where('key', 'shop_name')->value('value');
        $contact = SystemSetting::query()->where('key', 'shop_contact')->value('value');

        return [
            'name' => is_array($shopName) ? (string) ($shopName['name'] ?? config('mail.from.name', 'BNC Shop')) : (string) config('mail.from.name', 'BNC Shop'),
            'address' => is_array($contact) ? (string) ($contact['address'] ?? '') : '',
            'phone' => is_array($contact) ? (string) ($contact['phone'] ?? '') : '',
            'email' => is_array($contact) ? (string) ($contact['email'] ?? config('mail.from.address')) : (string) config('mail.from.address'),
            'jib' => filled(config('b2b.invoice.seller_jib')) ? (string) config('b2b.invoice.seller_jib') : null,
            'pdv_number' => filled(config('b2b.invoice.seller_pdv')) ? (string) config('b2b.invoice.seller_pdv') : null,
        ];
    }

    /**
     * @return array{
     *     rate_percent: float,
     *     currency: string,
     *     seller: array<string, mixed>,
     *     lines: list<array{
     *         product_name: string,
     *         product_sku: ?string,
     *         quantity: int,
     *         unit_net: float,
     *         line_net: float,
     *         vat_percent: float,
     *         vat_amount: float,
     *         line_gross: float,
     *     }>,
     *     items_net: float,
     *     discount_total: float,
     *     subtotal_before_discount: float,
     *     shipping_net: float,
     *     net_total: float,
     *     vat_total: float,
     *     gross_total: float,
     * }
     */
    public static function forOrder(B2bOrder $order): array
    {
        $order->loadMissing('items');

        $rate = self::rate();
        $ratePercent = self::ratePercent();

        $lines = $order->items
            ->map(function (B2bOrderItem $item) use ($rate, $ratePercent): array {
                $lineNet = round((float) $item->line_total, 2);
                $vatAmount = round($lineNet * $rate, 2);

                return [
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => (int) $item->quantity,
                    'unit_net' => round((float) $item->unit_final_price, 2),
                    'line_net' => $lineNet,
                    'vat_percent' => $ratePercent,
                    'vat_amount' => $vatAmount,
                    'line_gross' => round($lineNet + $vatAmount, 2),
                ];
            })
            ->values()
            ->all();

        $itemsNet = round(array_sum(array_column($lines, 'line_net')), 2);
        $shippingNet = round((float) $order->shipping_fee, 2);
        $netTotal = round($itemsNet + $shippingNet, 2);
        $vatTotal = round($netTotal * $rate, 2);

        return [
            'rate_percent' => $ratePercent,
            'currency' => (string) config('bnc.currency_symbol', 'KM'),
            'seller' => self::sellerDetails(),
            'lines' => $lines,
            'items_net' => $itemsNet,
            'discount_total' => round((float) $order->discount_total, 2),
            'subtotal_before_discount' => round((float) $order->subtotal, 2),
            'shipping_net' => $shippingNet,
            'net_total' => $netTotal,
            'vat_total' => $vatTotal,
            'gross_total' => round($netTotal + $vatTotal, 2),
        ];
    }

    public static function format(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
