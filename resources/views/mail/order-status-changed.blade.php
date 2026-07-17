<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status narudžbe</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#111111;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f6f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #ebebeb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 28px;background:#111111;color:#ffffff;">
                        <p style="margin:0;font-size:18px;font-weight:bold;">{{ $storeName }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                            Poštovani {{ $order->first_name }},
                        </p>

                        @if ($newStatus === 'poslano')
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
                                Vaša narudžba <strong>{{ $order->order_number }}</strong> je poslana i uskoro stiže.
                            </p>
                        @elseif ($newStatus === 'otkazano')
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
                                Vaša narudžba <strong>{{ $order->order_number }}</strong> je otkazana.
                                Za pitanja kontaktirajte našu podršku.
                            </p>
                        @else
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#474747;">
                                Status vaše narudžbe <strong>{{ $order->order_number }}</strong> je promijenjen.
                            </p>
                        @endif

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
                            <tr>
                                <td style="padding:16px;font-size:14px;line-height:1.6;">
                                    <strong>Broj narudžbe:</strong> {{ $order->order_number }}<br>
                                    <strong>Status:</strong> {{ $newStatusLabel }}<br>
                                    <strong>Ukupno:</strong> {{ number_format((float) $order->total, 2, ',', '.') }} KM
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#474747;">
                            Status možete provjeriti na linku ispod.
                        </p>

                        <p style="margin:0 0 24px;">
                            <a href="{{ $trackingUrl }}" style="display:inline-block;background:#e30613;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
                                Prati narudžbu
                            </a>
                        </p>

                        <p style="margin:0;font-size:12px;line-height:1.5;color:#737373;">
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
