Poštovani {{ $order->contact_name }},

Potvrđujemo vašu B2B narudžbu.

Broj narudžbe: {{ $order->order_number }}
Ukupno: {{ number_format((float) $order->total, 2, ',', '.') }} KM
Plaćanje: faktura

Stavke:
@foreach($order->items as $item)
- {{ $item->product_name }} x {{ $item->quantity }} — {{ number_format((float) $item->line_total, 2, ',', '.') }} KM
@endforeach

Adresa dostave:
{{ $order->shipping_address }}
@if($order->notes)

Napomena: {{ $order->notes }}
@endif

Hvala,
{{ config('app.name') }}

Kontakt: {{ config('b2b.mail.from_address', config('mail.from.address')) }}
