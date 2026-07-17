<x-mail::message>
# Potvrda B2B narudžbe

Broj narudžbe: **{{ $order->order_number }}**  
Ukupno: **{{ number_format((float) $order->total, 2, ',', '.') }} KM**  
Plaćanje: faktura

**Stavke:**
@foreach($order->items as $item)
- {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format((float) $item->line_total, 2, ',', '.') }} KM
@endforeach

**Adresa dostave:**  
{{ $order->shipping_address }}

@if($order->notes)
**Napomena:** {{ $order->notes }}
@endif

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
