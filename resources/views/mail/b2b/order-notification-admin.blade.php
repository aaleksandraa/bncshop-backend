<x-mail::message>
# Nova B2B narudžba

Broj: **{{ $order->order_number }}**  
Firma: **{{ $order->company_name }}**  
Kupac: {{ $order->contact_name }} ({{ $order->contact_email }})

@php($vat = \App\Support\B2bInvoiceVat::forOrder($order))

**Obračun:**
- Osnovica za PDV: **{{ \App\Support\B2bInvoiceVat::format($vat['net_total']) }} {{ $vat['currency'] }}**
- PDV {{ number_format($vat['rate_percent'], 0) }}%: **{{ \App\Support\B2bInvoiceVat::format($vat['vat_total']) }} {{ $vat['currency'] }}**
- **Ukupno za uplatu (sa PDV): {{ \App\Support\B2bInvoiceVat::format($vat['gross_total']) }} {{ $vat['currency'] }}**

@foreach($order->items as $item)
- {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format((float) $item->line_total, 2, ',', '.') }} KM (bez PDV)
@endforeach

<x-mail::button :url="url('/b2b-admin/b2b-orders/'.$order->id.'/edit')">
Otvori narudžbu
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
