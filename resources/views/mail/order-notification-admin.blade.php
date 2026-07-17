<!DOCTYPE html>
<html>
<body>
    <p>Nova narudžba: <strong>{{ $order->order_number }}</strong></p>
    <p>Kupac: {{ $order->first_name }} {{ $order->last_name }}</p>
    <p>Telefon: {{ $order->phone }}</p>
    <p>Ukupno: {{ number_format((float) $order->total, 2) }} {{ $currency }}</p>
</body>
</html>
