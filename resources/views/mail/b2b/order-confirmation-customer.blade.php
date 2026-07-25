<x-mail::message>
# Potvrda B2B narudžbe

Broj narudžbe: **{{ $order->order_number }}**  
Plaćanje: predračun

@php($vat = \App\Support\B2bInvoiceVat::forOrder($order))

**Stavke (bez PDV):**
@foreach($vat['lines'] as $line)
- {{ $line['product_name'] }} × {{ $line['quantity'] }} — {{ \App\Support\B2bInvoiceVat::format($line['line_net']) }} {{ $vat['currency'] }} (PDV {{ number_format($line['vat_percent'], 0) }}%: {{ \App\Support\B2bInvoiceVat::format($line['vat_amount']) }} {{ $vat['currency'] }})
@endforeach

**Obračun:**
- Roba ukupno (bez PDV): **{{ \App\Support\B2bInvoiceVat::format($vat['items_net']) }} {{ $vat['currency'] }}**
- Dostava (bez PDV): **{{ \App\Support\B2bInvoiceVat::format($vat['shipping_net']) }} {{ $vat['currency'] }}**
- Osnovica za PDV: **{{ \App\Support\B2bInvoiceVat::format($vat['net_total']) }} {{ $vat['currency'] }}**
- PDV {{ number_format($vat['rate_percent'], 0) }}%: **{{ \App\Support\B2bInvoiceVat::format($vat['vat_total']) }} {{ $vat['currency'] }}**
- **Ukupno za uplatu (sa PDV): {{ \App\Support\B2bInvoiceVat::format($vat['gross_total']) }} {{ $vat['currency'] }}**

**Adresa dostave:**  
{{ $order->shipping_address }}

@if($order->notes)
**Napomena:** {{ $order->notes }}
@endif

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
