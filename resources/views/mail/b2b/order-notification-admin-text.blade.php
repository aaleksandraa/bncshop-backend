Nova B2B narudžba

Broj: {{ $order->order_number }}
Firma: {{ $order->company_name }}
Kupac: {{ $order->contact_name }} ({{ $order->contact_email }})
Telefon: {{ $order->contact_phone }}
Ukupno: {{ number_format((float) $order->total, 2, ',', '.') }} KM

Stavke:
@foreach($order->items as $item)
- {{ $item->product_name }} x {{ $item->quantity }} — {{ number_format((float) $item->line_total, 2, ',', '.') }} KM
@endforeach

Adresa dostave:
{{ $order->shipping_address }}

Otvori narudžbu u adminu:
{{ rtrim((string) config('app.url'), '/').'/b2b-admin/b2b-orders/'.$order->id.'/edit' }}

{{ config('app.name') }}
