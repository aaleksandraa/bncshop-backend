<x-mail::message>
# Nova B2B narudžba

Broj: **{{ $order->order_number }}**  
Firma: **{{ $order->company_name }}**  
Kupac: {{ $order->contact_name }} ({{ $order->contact_email }})  
Ukupno: **{{ number_format((float) $order->total, 2, ',', '.') }} KM**

@foreach($order->items as $item)
- {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format((float) $item->line_total, 2, ',', '.') }} KM
@endforeach

<x-mail::button :url="url('/b2b-admin/b2b-orders/'.$order->id.'/edit')">
Otvori narudžbu
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
