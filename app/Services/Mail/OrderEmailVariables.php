<?php

namespace App\Services\Mail;

use App\Models\Order;
use App\Support\OrderDisplayLabels;
use App\Support\OrderStatus;

class OrderEmailVariables
{
    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function from(Order $order, array $extra = []): array
    {
        $order->loadMissing('items');

        $currency = (string) config('bnc.currency_symbol', 'KM');
        $storeName = (string) config('mail.from.name', 'BNC Shop');
        $trackingUrl = rtrim((string) config('bnc.frontend_url', config('app.url')), '/')
            .'/narudzba/'.$order->tracking_token;
        $isPickup = OrderDisplayLabels::isPickup($order);

        return array_merge([
            'order_number' => $order->order_number,
            'first_name' => $order->first_name,
            'last_name' => $order->last_name,
            'customer_name' => trim("{$order->first_name} {$order->last_name}"),
            'email' => (string) $order->email,
            'phone' => (string) $order->phone,
            'address' => (string) $order->address,
            'city' => (string) $order->city,
            'postal_code' => (string) $order->postal_code,
            'company_name' => (string) ($order->company_name ?? ''),
            'notes' => (string) ($order->notes ?? ''),
            'subtotal' => number_format((float) $order->subtotal, 2, ',', '.'),
            'discount_total' => number_format((float) $order->discount_total, 2, ',', '.'),
            'shipping_fee' => number_format((float) $order->shipping_fee, 2, ',', '.'),
            'total' => number_format((float) $order->total, 2, ',', '.'),
            'currency' => $currency,
            'items_count' => (string) $order->items_count,
            'items_table' => self::buildItemsTable($order, $currency),
            'payment_method' => OrderDisplayLabels::paymentMethodLabelForOrder($order),
            'shipping_method' => OrderDisplayLabels::shippingMethodLabel((string) $order->shipping_method),
            'shipping_method_label' => OrderDisplayLabels::shippingMethodLabel((string) $order->shipping_method),
            'shipping_summary_label' => OrderDisplayLabels::shippingSummaryLabel($order),
            'shipping_fee_display' => OrderDisplayLabels::shippingFeeDisplay($order),
            'is_pickup' => $isPickup ? '1' : '0',
            'pickup_notice' => $isPickup
                ? '<p style="margin:16px 0;padding:12px 16px;background:#fff8e6;border:1px solid #f0d48a;border-radius:8px;font-size:14px;line-height:1.6;color:#5c4a00;"><strong>Preuzimanje u poslovnici.</strong> Proizvod preuzimate u našoj poslovnici. Obavijestit ćemo vas e-mailom kada narudžba bude spremna.</p>'
                : '',
            'order_totals_box' => self::buildOrderTotalsBox($order, $currency),
            'tracking_url' => $trackingUrl,
            'store_name' => $storeName,
            'order_date' => $order->created_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
        ], $extra);
    }

    public static function buildItemsTable(Order $order, ?string $currency = null): string
    {
        $currency ??= (string) config('bnc.currency_symbol', 'KM');
        $rows = '';

        foreach ($order->items as $item) {
            $name = e($item->product_name);
            $qty = (int) $item->quantity;
            $line = number_format((float) $item->line_total, 2, ',', '.');

            $rows .= <<<HTML
<tr>
    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;font-size:14px;color:#333333;">{$name}</td>
    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;text-align:center;font-size:14px;color:#333333;">{$qty}</td>
    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;text-align:right;font-size:14px;color:#333333;">{$line} {$currency}</td>
</tr>
HTML;
        }

        if ($rows === '') {
            return '<p style="margin:16px 0;font-size:14px;color:#666666;">Nema stavki u narudžbi.</p>';
        }

        return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #ebebeb;border-radius:8px;overflow:hidden;margin:16px 0;">
    <tr style="background:#f5f5f5;">
        <th style="padding:10px 12px;text-align:left;font-size:13px;color:#111111;">Proizvod</th>
        <th style="padding:10px 12px;text-align:center;font-size:13px;color:#111111;">Kol.</th>
        <th style="padding:10px 12px;text-align:right;font-size:13px;color:#111111;">Iznos</th>
    </tr>
    {$rows}
</table>
HTML;
    }

    public static function buildOrderTotalsBox(Order $order, ?string $currency = null): string
    {
        $currency ??= (string) config('bnc.currency_symbol', 'KM');
        $subtotal = number_format((float) $order->subtotal, 2, ',', '.');
        $discount = (float) $order->discount_total;
        $shippingLabel = OrderDisplayLabels::isPickup($order) ? 'Preuzimanje u poslovnici' : 'Trošak dostave';
        $shippingDisplay = OrderDisplayLabels::shippingFeeDisplay($order);
        $total = number_format((float) $order->total, 2, ',', '.');

        $discountRow = $discount > 0
            ? '<tr><td style="padding:8px 0;font-size:14px;color:#666666;">Popust</td><td style="padding:8px 0;text-align:right;font-size:14px;color:#666666;">-'
                .number_format($discount, 2, ',', '.')." {$currency}</td></tr>"
            : '';

        return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
    <tr>
        <td style="padding:16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="padding:8px 0;font-size:14px;color:#666666;">Međuzbir</td>
                    <td style="padding:8px 0;text-align:right;font-size:14px;color:#333333;">{$subtotal} {$currency}</td>
                </tr>
                {$discountRow}
                <tr>
                    <td style="padding:8px 0;font-size:14px;color:#666666;">{$shippingLabel}</td>
                    <td style="padding:8px 0;text-align:right;font-size:14px;color:#333333;">{$shippingDisplay}</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:12px 0 0;border-top:1px solid #ebebeb;"></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;font-size:16px;font-weight:bold;color:#111111;">Ukupno</td>
                    <td style="padding:8px 0;text-align:right;font-size:16px;font-weight:bold;color:#111111;">{$total} {$currency}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
HTML;
    }

    /**
     * @return array<string, string>
     */
    public static function forStatusChange(Order $order, string $oldStatus, string $newStatus): array
    {
        return self::from($order, [
            'old_status' => OrderDisplayLabels::statusLabel($oldStatus, $order),
            'new_status' => OrderDisplayLabels::statusLabel($newStatus, $order),
        ]);
    }
}
