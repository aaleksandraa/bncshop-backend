Poštovani {{ $order->contact_name }},

Potvrđujemo vašu B2B narudžbu.

Broj narudžbe: {{ $order->order_number }}
Plaćanje: predračun

@include('mail.b2b.partials.order-vat-summary-text', ['order' => $order])

Adresa dostave:
{{ $order->shipping_address }}
@if($order->notes)

Napomena: {{ $order->notes }}
@endif

Predračun u PDF formatu dostupan je u B2B portalu i putem našeg tima.

Hvala,
{{ config('app.name') }}

Kontakt: {{ config('b2b.mail.from_address', config('mail.from.address')) }}
