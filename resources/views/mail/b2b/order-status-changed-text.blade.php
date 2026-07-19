Status B2B narudžbe ažuriran

Narudžba: {{ $order->order_number }}
Prethodni status: {{ \App\Support\B2bOrderStatus::label($previousStatus) }}
Novi status: {{ \App\Support\B2bOrderStatus::label($order->status) }}

Hvala,
{{ config('app.name') }}

Kontakt: {{ config('b2b.mail.from_address', config('mail.from.address')) }}
