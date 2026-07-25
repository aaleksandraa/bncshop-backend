<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle ?? 'Predračun' }} {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
            font-size: 10px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .header-top {
            width: 100%;
        }
        .header-top td { vertical-align: top; }
        .brand-cell { width: 58%; padding-right: 12px; }
        .meta-cell { width: 42%; }
        .logo {
            max-height: 42px;
            max-width: 150px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .company-line {
            margin-bottom: 2px;
            color: #333;
        }
        .doc-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            text-align: right;
            margin-bottom: 8px;
        }
        .doc-meta {
            text-align: right;
            font-size: 10px;
        }
        .doc-meta div { margin-bottom: 3px; }
        .parties {
            width: 100%;
            margin-bottom: 14px;
        }
        .parties td {
            vertical-align: top;
            width: 50%;
            padding-right: 14px;
        }
        .party-box {
            border: 1px solid #ccc;
            padding: 10px;
            min-height: 118px;
        }
        h2 {
            margin: 0 0 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .field { margin-bottom: 5px; }
        .field-label {
            display: block;
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.items th,
        table.items td {
            border: 1px solid #bbb;
            padding: 5px 6px;
        }
        table.items th {
            background: #f2f2f2;
            font-size: 8px;
            text-transform: uppercase;
        }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .totals-wrap {
            width: 100%;
            margin-top: 12px;
        }
        .totals-wrap td { vertical-align: top; }
        .notes {
            width: 58%;
            padding-right: 12px;
            font-size: 9px;
            color: #555;
        }
        .totals {
            width: 42%;
            border: 1px solid #bbb;
            padding: 8px 10px;
        }
        .totals .row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }
        .totals .label,
        .totals .value {
            display: table-cell;
        }
        .totals .value {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        .totals .divider {
            border-top: 1px solid #bbb;
            margin: 6px 0;
        }
        .total-row .label,
        .total-row .value {
            font-size: 11px;
            font-weight: bold;
        }
        .footer-note {
            margin-top: 14px;
            font-size: 9px;
            color: #666;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    @php
        $vat ??= \App\Support\B2bInvoiceVat::forOrder($order);
        $money = fn (float $amount): string => \App\Support\B2bInvoiceVat::format($amount);
        $documentTitle ??= 'Predračun';
        $paymentMethodLabel ??= \App\Support\B2bPaymentMethod::label($order->payment_method);
    @endphp

    <div class="header">
        <table class="header-top">
            <tr>
                <td class="brand-cell">
                    @if(!empty($logoDataUri))
                        <img src="{{ $logoDataUri }}" alt="{{ $vat['seller']['name'] }}" class="logo">
                    @endif
                    <div class="company-name">{{ $vat['seller']['name'] }}</div>
                    @if($vat['seller']['address'])
                        <div class="company-line">{{ $vat['seller']['address'] }}</div>
                    @endif
                    @if($vat['seller']['jib'])
                        <div class="company-line"><strong>JIB:</strong> {{ $vat['seller']['jib'] }}</div>
                    @endif
                    @if($vat['seller']['pdv_number'])
                        <div class="company-line"><strong>PDV broj:</strong> {{ $vat['seller']['pdv_number'] }}</div>
                    @endif
                    <div class="company-line">
                        {{ $vat['seller']['email'] }}@if($vat['seller']['phone']) · {{ $vat['seller']['phone'] }}@endif
                    </div>
                </td>
                <td class="meta-cell">
                    <div class="doc-title">{{ $documentTitle }}</div>
                    <div class="doc-meta">
                        <div><strong>Broj predračuna:</strong> {{ $order->order_number }}</div>
                        <div><strong>Datum:</strong> {{ $order->created_at?->format('d.m.Y') }}</div>
                        <div><strong>Valuta:</strong> {{ $vat['currency'] }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <div class="party-box">
                    <h2>Prodavac</h2>
                    <div class="field">
                        <span class="field-label">Naziv</span>
                        {{ $vat['seller']['name'] }}
                    </div>
                    @if($vat['seller']['address'])
                        <div class="field">
                            <span class="field-label">Adresa</span>
                            {{ $vat['seller']['address'] }}
                        </div>
                    @endif
                    @if($vat['seller']['jib'])
                        <div class="field">
                            <span class="field-label">JIB</span>
                            {{ $vat['seller']['jib'] }}
                        </div>
                    @endif
                    @if($vat['seller']['pdv_number'])
                        <div class="field">
                            <span class="field-label">PDV broj</span>
                            {{ $vat['seller']['pdv_number'] }}
                        </div>
                    @endif
                    <div class="field">
                        <span class="field-label">Kontakt</span>
                        {{ $vat['seller']['email'] }}@if($vat['seller']['phone']) · {{ $vat['seller']['phone'] }}@endif
                    </div>
                </div>
            </td>
            <td>
                <div class="party-box">
                    <h2>Kupac</h2>
                    <div class="field">
                        <span class="field-label">Naziv</span>
                        {{ $order->company_name }}
                    </div>
                    @if($order->company_address)
                        <div class="field">
                            <span class="field-label">Adresa</span>
                            {{ $order->company_address }}
                        </div>
                    @endif
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
                        {{ $order->contact_name }} · {{ $order->contact_email }} · {{ $order->contact_phone }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="center">R.br</th>
                <th>Naziv artikla</th>
                <th>Šifra</th>
                <th class="center">JM</th>
                <th class="num">Kol.</th>
                <th class="num">Cijena bez PDV</th>
                <th class="num">Iznos bez PDV</th>
                <th class="center">PDV %</th>
                <th class="num">Iznos PDV</th>
                <th class="num">Iznos sa PDV</th>
            </tr>
        </thead>
        <tbody>
            @foreach($vat['lines'] as $index => $line)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $line['product_name'] }}</td>
                    <td>{{ $line['product_sku'] ?? '—' }}</td>
                    <td class="center">kom</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $money($line['unit_net']) }}</td>
                    <td class="num">{{ $money($line['line_net']) }}</td>
                    <td class="center">{{ number_format($line['vat_percent'], 0) }}%</td>
                    <td class="num">{{ $money($line['vat_amount']) }}</td>
                    <td class="num">{{ $money($line['line_gross']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td class="notes">
                <strong>Adresa dostave:</strong> {{ $order->shipping_address }}<br>
                <strong>Način plaćanja:</strong> {{ $paymentMethodLabel }}<br>
                @if($order->notes)
                    <strong>Napomena:</strong> {{ $order->notes }}
                @endif
            </td>
            <td>
                <div class="totals">
                    @if($vat['discount_total'] > 0)
                        <div class="row">
                            <span class="label">Roba prije popusta (bez PDV)</span>
                            <span class="value">{{ $money($vat['subtotal_before_discount']) }} {{ $vat['currency'] }}</span>
                        </div>
                        <div class="row">
                            <span class="label">Popust (bez PDV)</span>
                            <span class="value">-{{ $money($vat['discount_total']) }} {{ $vat['currency'] }}</span>
                        </div>
                    @endif
                    <div class="row">
                        <span class="label">Roba ukupno (bez PDV)</span>
                        <span class="value">{{ $money($vat['items_net']) }} {{ $vat['currency'] }}</span>
                    </div>
                    <div class="row">
                        <span class="label">Dostava (bez PDV)</span>
                        <span class="value">{{ $money($vat['shipping_net']) }} {{ $vat['currency'] }}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="row">
                        <span class="label">Osnovica za PDV</span>
                        <span class="value">{{ $money($vat['net_total']) }} {{ $vat['currency'] }}</span>
                    </div>
                    <div class="row">
                        <span class="label">PDV {{ number_format($vat['rate_percent'], 0) }}%</span>
                        <span class="value">{{ $money($vat['vat_total']) }} {{ $vat['currency'] }}</span>
                    </div>
                    <div class="divider"></div>
                    <div class="row total-row">
                        <span class="label">Ukupno za uplatu (sa PDV)</span>
                        <span class="value">{{ $money($vat['gross_total']) }} {{ $vat['currency'] }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <p class="footer-note">
        Ovaj dokument je predračun informativnog karaktera i ne predstavlja konačni fiskalni račun.
        Sve cijene artikala i dostave iskazane su bez PDV-a. PDV je obračunat po stopi od {{ number_format($vat['rate_percent'], 0) }}% u skladu sa važećim propisima Bosne i Hercegovine.
    </p>
</body>
</html>
