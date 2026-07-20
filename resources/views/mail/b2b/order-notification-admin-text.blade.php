Nova B2B narudžba

Broj: {{ $order->order_number }}
Firma: {{ $order->company_name }}
Kupac: {{ $order->contact_name }} ({{ $order->contact_email }})
Telefon: {{ $order->contact_phone }}

@include('mail.b2b.partials.order-vat-summary-text', ['order' => $order])

Adresa dostave:
{{ $order->shipping_address }}

Otvori narudžbu u adminu:
{{ rtrim((string) config('app.url'), '/').'/b2b-admin/b2b-orders/'.$order->id.'/edit' }}

{{ config('app.name') }}
