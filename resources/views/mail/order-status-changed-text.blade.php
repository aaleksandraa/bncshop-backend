Poštovani {{ $order->first_name }},

@if ($newStatus === 'poslano')
Vaša narudžba {{ $order->order_number }} je poslana i uskoro stiže.
@elseif ($newStatus === 'otkazano')
Vaša narudžba {{ $order->order_number }} je otkazana. Za pitanja kontaktirajte našu podršku.
@else
Status vaše narudžbe {{ $order->order_number }} je promijenjen.
@endif

Broj narudžbe: {{ $order->order_number }}
Status: {{ $newStatusLabel }}
Ukupno: {{ number_format((float) $order->total, 2, ',', '.') }} KM

Pratite narudžbu: {{ $trackingUrl }}

Hvala vam na povjerenju.
{{ $storeName }}
