<?php

namespace App\Support;

class EmailTemplateLayouts
{
    public static function wrap(string $headline, string $bodyContent, bool $withTrackingButton = false): string
    {
        $trackingButton = $withTrackingButton
            ? <<<'HTML'
<p style="margin:0 0 24px;">
    <a href="{{tracking_url}}" style="display:inline-block;background:#e30613;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:bold;">
        Prati narudžbu
    </a>
</p>
HTML
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$headline}</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,Helvetica,sans-serif;color:#111111;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f6f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #ebebeb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:24px 28px;background:#111111;color:#ffffff;">
                        <p style="margin:0;font-size:18px;font-weight:bold;">{{store_name}}</p>
                        <p style="margin:8px 0 0;font-size:14px;color:#dddddd;">{$headline}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        {$bodyContent}
                        {$trackingButton}
                        <p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#737373;border-top:1px solid #ebebeb;padding-top:16px;">
                            Hvala vam na povjerenju.<br>
                            {{store_name}}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
HTML;
    }

    public static function summaryBox(): string
    {
        return <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
    <tr>
        <td style="padding:16px;font-size:14px;line-height:1.8;color:#333333;">
            <strong>Broj narudžbe:</strong> {{order_number}}<br>
            <strong>Datum:</strong> {{order_date}}<br>
            <strong>Način plaćanja:</strong> {{payment_method}}<br>
            <strong>Dostava:</strong> {{shipping_method}}<br>
            <strong>Ukupno:</strong> {{total}} {{currency}}
        </td>
    </tr>
</table>
HTML;
    }

    public static function customerBox(): string
    {
        return <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;background:#fbfbfb;border:1px solid #ebebeb;border-radius:8px;">
    <tr>
        <td style="padding:16px;font-size:14px;line-height:1.8;color:#333333;">
            <strong>Kupac:</strong> {{customer_name}}<br>
            <strong>E-mail:</strong> {{email}}<br>
            <strong>Telefon:</strong> {{phone}}<br>
            <strong>Adresa:</strong> {{address}}, {{postal_code}} {{city}}
        </td>
    </tr>
</table>
HTML;
    }
}
