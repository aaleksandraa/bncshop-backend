<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>Faktura {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
            font-size: 11px;
            line-height: 1.45;
        }
        .header {
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .store-name {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
        }
        h1 { margin: 4px 0 0; font-size: 20px; }
        h2 {
            margin: 0 0 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .section { margin-bottom: 16px; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; padding-right: 12px; }
        .field { margin-bottom: 6px; }
        .field-label {
            display: block;
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td {
            border: 1px solid #ccc;
            padding: 6px 8px;
        }
        table.items th { background: #f3f3f3; }
        .num { text-align: right; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals .row { display: table; width: 100%; margin-bottom: 4px; }
        .totals .label, .totals .value { display: table-cell; }
        .totals .value { text-align: right; font-weight: bold; }
        .total-row .value { font-size: 14px; }
        .note { margin-top: 16px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    @php
        $currency = config('bnc.currency_symbol', 'KM');
        $storeName = config('mail.from.name', 'BNC Shop');
        $itemsSubtotal = (float) $order->subtotal - (float) $order->discount_total;
    @endphp

    <div class="header">
        <div class="store-name">{{ $storeName }} — B2B faktura</div>
        <h1>{{ $order->order_number }}</h1>
        <div>Datum: {{ $order->created_at?->format('d.m.Y H:i') }}</div>
    </div>

    <div class="section">
        <table class="grid">
            <tr>
                <td>
                    <h2>Kupac</h2>
                    <div class="field">
                        <span class="field-label">Firma</span>
                        {{ $order->company_name }}
                    </div>
                    <div class="field">
                        <span class="field-label">JIB</span>
                        {{ $order->jib }}
                    </div>
                    @if($order->pdv_number)
                        <div class="field">
                            <span class="field-label">PDV broj</span>
                            {{ $order->pdv_number }}
                        </div>
                    @endif
                    <div class="field">
                        <span class="field-label">Kontakt</span>
                        {{ $order->contact_name }}
                    </div>
                    <div class="field">
                        <span class="field-label">Email</span>
                        {{ $order->contact_email }}
                    </div>
                    <div class="field">
                        <span class="field-label">Telefon</span>
                        {{ $order->contact_phone }}
                    </div>
                </td>
                <td>
                    <h2>Dostava</h2>
                    <div class="field">
                        <span class="field-label">Adresa</span>
                        {{ $order->shipping_address }}
                    </div>
                    <div class="field">
                        <span class="field-label">Plaćanje</span>
                        Faktura
                    </div>
                    @if($order->notes)
                        <div class="field">
                            <span class="field-label">Napomena</span>
                            {{ $order->notes }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Stavke</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Proizvod</th>
                    <th>Šifra</th>
                    <th class="num">Kol.</th>
                    <th class="num">Cijena</th>
                    <th class="num">Ukupno</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->product_sku ?? '—' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ number_format((float) $item->unit_final_price, 2, ',', '.') }} {{ $currency }}</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <div class="row">
            <span class="label">Međuzbir</span>
            <span class="value">{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $currency }}</span>
        </div>
        @if((float) $order->discount_total > 0)
            <div class="row">
                <span class="label">Popust</span>
                <span class="value">-{{ number_format((float) $order->discount_total, 2, ',', '.') }} {{ $currency }}</span>
            </div>
        @endif
        <div class="row">
            <span class="label">Dostava</span>
            <span class="value">{{ number_format((float) $order->shipping_fee, 2, ',', '.') }} {{ $currency }}</span>
        </div>
        <div class="row total-row">
            <span class="label">Ukupno</span>
            <span class="value">{{ number_format((float) $order->total, 2, ',', '.') }} {{ $currency }}</span>
        </div>
    </div>

    <p class="note">PDV nije uključen u cijenu.</p>
</body>
</html>
