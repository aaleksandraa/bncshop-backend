@php
    $vat ??= \App\Support\B2bInvoiceVat::forOrder($order);
    $money = fn (float $amount): string => \App\Support\B2bInvoiceVat::format($amount);
@endphp

Stavke (iznos bez PDV):
@foreach($vat['lines'] as $line)
- {{ $line['product_name'] }} x {{ $line['quantity'] }} — {{ $money($line['line_net']) }} {{ $vat['currency'] }} (PDV {{ number_format($line['vat_percent'], 0) }}%: {{ $money($line['vat_amount']) }} {{ $vat['currency'] }})
@endforeach

Obračun:
Roba ukupno (bez PDV): {{ $money($vat['items_net']) }} {{ $vat['currency'] }}
Dostava (bez PDV): {{ $money($vat['shipping_net']) }} {{ $vat['currency'] }}
Osnovica za PDV: {{ $money($vat['net_total']) }} {{ $vat['currency'] }}
PDV {{ number_format($vat['rate_percent'], 0) }}%: {{ $money($vat['vat_total']) }} {{ $vat['currency'] }}
Ukupno za uplatu (sa PDV): {{ $money($vat['gross_total']) }} {{ $vat['currency'] }}
