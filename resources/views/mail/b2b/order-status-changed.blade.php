<x-mail::message>
# Status B2B narudžbe ažuriran

Narudžba **{{ $order->order_number }}**  
Prethodni status: {{ \App\Support\B2bOrderStatus::label($previousStatus) }}  
Novi status: **{{ \App\Support\B2bOrderStatus::label($order->status) }}**

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
