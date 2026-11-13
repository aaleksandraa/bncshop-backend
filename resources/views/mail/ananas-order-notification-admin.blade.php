<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Ananas narudžba</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#111111;">
@php
    $storeName = (string) config('mail.from.name', 'BNC Shop');
    $currency = $order->currency ?: 'KM';
    $address = is_array($order->billing_address) ? $order->billing_address : [];
    $street = trim(implode(' ', array_filter([
        $address['streetName'] ?? '',
        $address['streetNumber'] ?? '',
        $address['apartmentNumber'] ?? '',
    ])));
    $cityLine = trim(implode(' ', array_filter([
        $address['postcode'] ?? '',
        $address['city'] ?? '',
    ])));
    $adminUrl = url('/admin/ananas-orders/'.$order->id);
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f6f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #ebebeb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 28px;background:#111111;color:#ffffff;">
                        <p style="margin:0;font-size:18px;font-weight:bold;">{{ $storeName }}</p>
                        <p style="margin:8px 0 0;font-size:14px;color:#dddddd;">Nova Ananas narudžba</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
                            Stigla je nova Ananas narudžba <strong>{{ $order->ananas_order_id }}</strong>.
                            Otvorite je u adminu i potvrdite količinu.
                        </p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
                            <tr>
                                <td style="padding:16px;font-size:14px;line-height:1.8;color:#333333;">
                                    <strong>Ananas id:</strong> {{ $order->ananas_order_id }}<br>
                                    <strong>Datum:</strong> {{ $order->ananas_created_at?->format('d.m.Y H:i') ?? '—' }}<br>
                                    <strong>Način plaćanja:</strong> {{ $order->paymentLabel() }}<br>
                                    <strong>Ukupno:</strong> {{ number_format((float) $order->total_price, 2, ',', '.') }} {{ $currency }}<br>
                                    <strong>Status:</strong> {{ $order->statusLabel() }}
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
                            <tr>
                                <td style="padding:16px;font-size:14px;line-height:1.8;color:#333333;">
                                    <strong>Kupac:</strong> {{ $order->customerName() }}<br>
                                    <strong>Adresa:</strong> {{ $street !== '' ? $street.', ' : '' }}{{ $cityLine }}
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 8px;font-size:15px;font-weight:bold;color:#111111;">Stavke narudžbe</p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #ebebeb;border-radius:8px;overflow:hidden;margin:16px 0;">
                            <tr style="background:#f5f5f5;">
                                <th style="padding:10px 12px;text-align:left;font-size:13px;color:#111111;">Proizvod</th>
                                <th style="padding:10px 12px;text-align:left;font-size:13px;color:#111111;">EAN</th>
                                <th style="padding:10px 12px;text-align:center;font-size:13px;color:#111111;">Kol.</th>
                                <th style="padding:10px 12px;text-align:right;font-size:13px;color:#111111;">Iznos</th>
                            </tr>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;font-size:14px;color:#333333;">{{ $item->product_name }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;font-size:14px;color:#333333;">{{ $item->product_ean }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;text-align:center;font-size:14px;color:#333333;">{{ $item->quantity_ordered }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #ebebeb;text-align:right;font-size:14px;color:#333333;">
                                        {{ number_format((float) ($item->line_total ?? $item->unit_price), 2, ',', '.') }} {{ $currency }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        <p style="margin:0 0 24px;">
                            <a href="{{ $adminUrl }}" style="display:inline-block;background:#e30613;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
                                Otvori narudžbu
                            </a>
                        </p>
                        <p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#737373;border-top:1px solid #ebebeb;padding-top:16px;">
                            Hvala vam na povjerenju.<br>
                            {{ $storeName }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
