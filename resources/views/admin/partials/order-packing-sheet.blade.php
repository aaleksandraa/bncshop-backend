@php
    $shippingLabels = [
        'delivery' => 'Dostava',
        'pickup' => 'Preuzimanje u radnji',
    ];
    $paymentLabels = [
        'pay_on_delivery' => 'Pouzećem',
        'cod' => 'Pouzećem',
        'bank_transfer' => 'Virman',
        'card' => 'Kartica',
    ];
    $storeName = config('mail.from.name', 'BNC Shop');
    $currency = config('bnc.currency_symbol', 'KM');

    $itemCode = static fn ($item): string => $item->displayCode();
@endphp

<div class="packing-sheet">
    <header class="packing-header">
        <div class="packing-header-main">
            <div class="store-name">{{ $storeName }} — list za pakovanje</div>
            <div class="order-number">{{ $order->order_number }}</div>
            <div class="order-date">{{ $order->created_at?->format('d.m.Y H:i') }}</div>
        </div>
        <div class="packing-header-meta">
            <div><span class="meta-label">Dostava</span> {{ $shippingLabels[$order->shipping_method] ?? $order->shipping_method }}</div>
            <div><span class="meta-label">Plaćanje</span> {{ $paymentLabels[$order->payment_method] ?? $order->payment_method }}</div>
            <div><span class="meta-label">Stavki</span> {{ $order->items_count ?? $order->items->count() }}</div>
        </div>
    </header>

    <section class="address-block">
        <h2>Adresa za dostavu</h2>
        <p class="customer-name">{{ $order->first_name }} {{ $order->last_name }}</p>
        <p class="address-line">{{ $order->address }}</p>
        <p class="address-line"><strong>{{ $order->postal_code }} {{ $order->city }}</strong></p>
        <p class="contact-line">Tel: {{ $order->phone ?: '—' }}</p>
        @if(filled($order->email))
            <p class="contact-line">E-mail: {{ $order->email }}</p>
        @endif
    </section>

    @if(filled($order->notes))
        <section class="notes-block">
            <h2>Napomena kupca</h2>
            <p>{{ $order->notes }}</p>
        </section>
    @endif

    <section class="items-block">
        <h2>Stavke za pakovanje ({{ $order->items->sum('quantity') }} kom)</h2>
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th class="col-product">Proizvod</th>
                    <th class="col-code">Šifra</th>
                    <th class="col-qty">Kol.</th>
                    <th class="col-price">Cijena</th>
                    <th class="col-total">Ukupno</th>
                    <th class="col-check">✓</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                    @php($code = $itemCode($item))
                    <tr>
                        <td class="col-index">{{ $index + 1 }}</td>
                        <td class="col-product">
                            <span class="product-name">{{ $item->product_name }}</span>
                            @if(filled($item->brand_name))
                                <span class="product-brand">{{ $item->brand_name }}</span>
                            @endif
                        </td>
                        <td class="col-code">
                            @if($code !== '')
                                <span class="product-code">{{ $code }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="col-qty"><strong>{{ $item->quantity }}</strong></td>
                        <td class="col-price">{{ number_format((float) $item->final_price, 2, ',', '.') }}</td>
                        <td class="col-total"><strong>{{ number_format((float) $item->line_total, 2, ',', '.') }}</strong></td>
                        <td class="col-check"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="summary-block">
        <div class="summary-box">
            <div class="summary-row">
                <span>Međuzbir</span>
                <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $currency }}</span>
            </div>
            @if((float) $order->discount_total > 0)
                <div class="summary-row">
                    <span>Popust</span>
                    <span>−{{ number_format((float) $order->discount_total, 2, ',', '.') }} {{ $currency }}</span>
                </div>
            @endif
            <div class="summary-row">
                <span>Dostava</span>
                <span>{{ number_format((float) $order->shipping_fee, 2, ',', '.') }} {{ $currency }}</span>
            </div>
            @if((float) $order->loyalty_discount_amount > 0)
                <div class="summary-row">
                    <span>Loyalty</span>
                    <span>−{{ number_format((float) $order->loyalty_discount_amount, 2, ',', '.') }} {{ $currency }}</span>
                </div>
            @endif
            <div class="summary-row summary-total">
                <span>Ukupno za naplatu</span>
                <span>{{ number_format((float) $order->total, 2, ',', '.') }} {{ $currency }}</span>
            </div>
        </div>
    </section>
</div>
