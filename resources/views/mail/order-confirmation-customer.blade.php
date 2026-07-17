<!DOCTYPE html>
<html>
<body>
    <p>Poštovani {{ $order->first_name }},</p>
    <p>Hvala na narudžbi <strong>{{ $order->order_number }}</strong>.</p>
    <p>Ukupno: <strong>{{ number_format((float) $order->total, 2) }} {{ $currency }}</strong></p>
    <p>Broj stavki: {{ $order->items_count }}</p>
</body>
</html>
