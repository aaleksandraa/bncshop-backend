@php
    use App\Support\OrderStatus;

    $paymentLabels = [
        'pay_on_delivery' => 'Plaćanje pouzećem',
        'cod' => 'Plaćanje pouzećem',
        'bank_transfer' => 'Virman',
        'card' => 'Kartica',
    ];
    $shippingLabels = [
        'delivery' => 'Dostava',
        'pickup' => 'Preuzimanje u radnji',
    ];
    $currency = config('bnc.currency_symbol', 'KM');
@endphp

<div class="order-sheet">
    <div class="order-header">
        <div class="store-name">{{ config('mail.from.name', 'BNC Shop') }}</div>
        <h1>Narudžba {{ $order->order_number }}</h1>
        <div>{{ $order->created_at?->format('d.m.Y H:i') }} · {{ OrderStatus::label((string) $order->status) }}</div>
    </div>

    <div class="section">
        <h2>Kupac</h2>
        <table class="grid">
            <tr>
                <td>
                    <div class="field"><span class="field-label">Ime i prezime</span><strong>{{ $order->first_name }} {{ $order->last_name }}</strong></div>
                    <div class="field"><span class="field-label">Telefon</span>{{ $order->phone ?: '—' }}</div>
                    <div class="field"><span class="field-label">E-mail</span>{{ $order->email ?: '—' }}</div>
                </td>
                <td>
                    <div class="field"><span class="field-label">Adresa</span>{{ $order->address ?: '—' }}</div>
                    <div class="field"><span class="field-label">Grad</span>{{ $order->postal_code }} {{ $order->city }}</div>
                    <div class="field"><span class="field-label">Plaćanje / Dostava</span>{{ $paymentLabels[$order->payment_method] ?? $order->payment_method }} · {{ $shippingLabels[$order->shipping_method] ?? $order->shipping_method }}</div>
                </td>
            </tr>
        </table>
        @if(filled($order->notes))
            <div class="field"><span class="field-label">Napomene</span>{{ $order->notes }}</div>
        @endif
    </div>

    <div class="section">
        <h2>Stavke</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Proizvod</th>
                    <th>SKU</th>
                    <th class="num">Kol.</th>
                    <th class="num">Cijena</th>
                    <th class="num">Ukupno</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->displayCode() ?: '—' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ number_format((float) $item->final_price, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section totals">
        <div class="field"><span class="field-label">Međuzbir</span><span class="field-value">{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $currency }}</span></div>
        <div class="field"><span class="field-label">Popust</span><span class="field-value">{{ number_format((float) $order->discount_total, 2, ',', '.') }} {{ $currency }}</span></div>
        <div class="field"><span class="field-label">Dostava</span><span class="field-value">{{ number_format((float) $order->shipping_fee, 2, ',', '.') }} {{ $currency }}</span></div>
        <div class="field total-row"><span class="field-label">Ukupno</span><span class="field-value">{{ number_format((float) $order->total, 2, ',', '.') }} {{ $currency }}</span></div>
    </div>
</div>
