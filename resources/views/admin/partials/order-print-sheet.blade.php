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
    $storeName = config('mail.from.name', 'BNC Shop');
@endphp

<div class="order-sheet">
    <header class="order-header">
        <div>
            <div class="store-name">{{ $storeName }}</div>
            <h1>Narudžba {{ $order->order_number }}</h1>
        </div>
        <div class="order-meta">
            <div><strong>Datum:</strong> {{ $order->created_at?->format('d.m.Y H:i') }}</div>
            <div><strong>Status:</strong> {{ OrderStatus::label((string) $order->status) }}</div>
            <div><strong>Plaćanje:</strong> {{ $paymentLabels[$order->payment_method] ?? $order->payment_method }}</div>
            <div><strong>Dostava:</strong> {{ $shippingLabels[$order->shipping_method] ?? $order->shipping_method }}</div>
        </div>
    </header>

    <section class="section">
        <h2>Kupac</h2>
        <div class="grid two-col">
            <div>
                <div class="field"><span>Ime i prezime</span><strong>{{ $order->first_name }} {{ $order->last_name }}</strong></div>
                <div class="field"><span>Telefon</span><strong>{{ $order->phone ?: '—' }}</strong></div>
                <div class="field"><span>E-mail</span><strong>{{ $order->email ?: '—' }}</strong></div>
            </div>
            <div>
                <div class="field"><span>Adresa</span><strong>{{ $order->address ?: '—' }}</strong></div>
                <div class="field"><span>Grad / Poštanski broj</span><strong>{{ $order->postal_code }} {{ $order->city }}</strong></div>
                @if(filled($order->company_name))
                    <div class="field"><span>Firma</span><strong>{{ $order->company_name }}</strong></div>
                @endif
                @if(filled($order->jib))
                    <div class="field"><span>JIB</span><strong>{{ $order->jib }}</strong></div>
                @endif
                @if(filled($order->pdv_number))
                    <div class="field"><span>PDV</span><strong>{{ $order->pdv_number }}</strong></div>
                @endif
            </div>
        </div>
        @if(filled($order->notes))
            <div class="field notes"><span>Napomene kupca</span><strong>{{ $order->notes }}</strong></div>
        @endif
    </section>

    <section class="section">
        <h2>Stavke</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Proizvod</th>
                    <th>SKU</th>
                    <th>Brend</th>
                    <th class="num">Kol.</th>
                    <th class="num">Cijena</th>
                    <th class="num">Ukupno</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->displayCode() ?: '—' }}</td>
                        <td>{{ $item->brand_name ?: '—' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ number_format((float) $item->final_price, 2, ',', '.') }} {{ $currency }}</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $currency }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Nema stavki.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="section totals">
        <div class="totals-grid">
            <div class="field"><span>Međuzbir</span><strong>{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $currency }}</strong></div>
            <div class="field"><span>Popust</span><strong>{{ number_format((float) $order->discount_total, 2, ',', '.') }} {{ $currency }}</strong></div>
            <div class="field"><span>Dostava</span><strong>{{ number_format((float) $order->shipping_fee, 2, ',', '.') }} {{ $currency }}</strong></div>
            @if((float) $order->loyalty_discount_amount > 0)
                <div class="field"><span>Loyalty popust</span><strong>{{ number_format((float) $order->loyalty_discount_amount, 2, ',', '.') }} {{ $currency }}</strong></div>
            @endif
            @if($order->coupon)
                <div class="field"><span>Kupon</span><strong>{{ $order->coupon->code }}</strong></div>
            @endif
            <div class="field total"><span>Ukupno</span><strong>{{ number_format((float) $order->total, 2, ',', '.') }} {{ $currency }}</strong></div>
        </div>
    </section>

    @if($order->statusHistory->isNotEmpty())
        <section class="section">
            <h2>Historija statusa</h2>
            <table class="compact">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Promjena</th>
                        <th>Korisnik</th>
                        <th>Napomena</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->statusHistory->sortByDesc('created_at') as $history)
                        <tr>
                            <td>{{ $history->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ OrderStatus::label((string) $history->old_status) }} → {{ OrderStatus::label((string) $history->new_status) }}</td>
                            <td>{{ $history->changedByUser?->name ?? 'Sistem' }}</td>
                            <td>{{ $history->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
