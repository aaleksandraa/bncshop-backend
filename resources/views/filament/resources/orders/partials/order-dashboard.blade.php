@php
    use App\Support\OrderStatus;

    $currency = config('bnc.currency_symbol', 'KM');
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

    $currentStatus = OrderStatus::normalize($order->status);
    $statusLabel = OrderStatus::label($currentStatus);
    $statusHeroClass = match ($currentStatus) {
        'isporučeno' => 'order-status-hero--success',
        'otkazano', 'vraćeno', 'neuspjela_dostava' => 'order-status-hero--danger',
        'potvrđena', 'spakovano', 'poslano' => 'order-status-hero--amber',
        default => 'order-status-hero--neutral',
    };
    $latestStatusChange = $order->statusHistory->sortByDesc('created_at')->first();

    $trackingUrl = filled($order->tracking_token)
        ? rtrim((string) config('bnc.frontend_url', config('app.url')), '/').'/narudzba/'.$order->tracking_token
        : null;

    $money = static fn (float|string|null $amount): string => number_format((float) $amount, 2, ',', '.').' '.$currency;
@endphp

<style>
    .order-view {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .order-panel {
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(255 255 255);
    }

    .dark .order-panel {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .order-panel__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border-bottom: 1px solid rgb(243 244 246);
        padding: 0.875rem 1.25rem;
        background: rgb(249 250 251);
    }

    .dark .order-panel__head {
        border-bottom-color: rgb(55 65 81);
        background: rgb(31 41 55);
    }

    .order-panel__title {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgb(55 65 81);
    }

    .dark .order-panel__title {
        color: rgb(209 213 219);
    }

    .order-panel__body {
        padding: 1.25rem;
    }

    .order-meta-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(255 255 255);
        padding: 1rem 1.25rem;
    }

    .dark .order-meta-bar {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .order-meta-items {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 1rem;
        font-size: 0.875rem;
        color: rgb(107 114 128);
    }

    .dark .order-meta-items {
        color: rgb(156 163 175);
    }

    .order-meta-total {
        text-align: right;
    }

    .order-meta-total__label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .order-meta-total__value {
        font-size: 1.5rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: rgb(17 24 39);
    }

    .dark .order-meta-total__value {
        color: rgb(245 158 11);
    }

    .order-badge-neutral,
    .order-badge-amber,
    .order-badge-success,
    .order-badge-danger {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.125rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25rem;
    }

    .order-badge-neutral {
        background: rgb(243 244 246);
        color: rgb(55 65 81);
    }

    .dark .order-badge-neutral {
        background: rgb(55 65 81);
        color: rgb(229 231 235);
    }

    .order-badge-amber {
        background: rgb(254 243 199);
        color: rgb(146 64 14);
    }

    .dark .order-badge-amber {
        background: rgb(120 53 15 / 0.35);
        color: rgb(252 211 77);
    }

    .order-badge-success {
        background: rgb(209 250 229);
        color: rgb(6 95 70);
    }

    .dark .order-badge-success {
        background: rgb(6 78 59 / 0.35);
        color: rgb(110 231 183);
    }

    .order-badge-danger {
        background: rgb(254 226 226);
        color: rgb(153 27 27);
    }

    .dark .order-badge-danger {
        background: rgb(127 29 29 / 0.35);
        color: rgb(252 165 165);
    }

    .order-status-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem 1.5rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        border-left-width: 4px;
        background: rgb(255 255 255);
        padding: 1.125rem 1.25rem;
    }

    .dark .order-status-hero {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .order-status-hero--neutral { border-left-color: rgb(107 114 128); }
    .order-status-hero--amber {
        border-left-color: rgb(217 119 6);
        background: rgb(255 251 235);
    }
    .dark .order-status-hero--amber { background: rgb(120 53 15 / 0.15); }
    .order-status-hero--success {
        border-left-color: rgb(5 150 105);
        background: rgb(236 253 245);
    }
    .dark .order-status-hero--success { background: rgb(6 78 59 / 0.15); }
    .order-status-hero--danger {
        border-left-color: rgb(220 38 38);
        background: rgb(254 242 242);
    }
    .dark .order-status-hero--danger { background: rgb(127 29 29 / 0.15); }

    .order-status-hero__main {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 12rem;
    }

    .order-status-hero__label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .dark .order-status-hero__label { color: rgb(156 163 175); }

    .order-status-hero__value {
        font-size: 1.625rem;
        font-weight: 800;
        line-height: 1.2;
        color: rgb(17 24 39);
    }

    .dark .order-status-hero__value { color: rgb(243 244 246); }
    .order-status-hero--amber .order-status-hero__value { color: rgb(146 64 14); }
    .dark .order-status-hero--amber .order-status-hero__value { color: rgb(252 211 77); }
    .order-status-hero--success .order-status-hero__value { color: rgb(6 95 70); }
    .dark .order-status-hero--success .order-status-hero__value { color: rgb(110 231 183); }
    .order-status-hero--danger .order-status-hero__value { color: rgb(153 27 27); }
    .dark .order-status-hero--danger .order-status-hero__value { color: rgb(252 165 165); }

    .order-status-hero__meta {
        flex: 1 1 16rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: rgb(75 85 99);
    }

    .dark .order-status-hero__meta { color: rgb(156 163 175); }
    .order-status-hero__meta strong { color: rgb(17 24 39); }
    .dark .order-status-hero__meta strong { color: rgb(229 231 235); }

    .order-status-hero__hint {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .order-fields {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1rem 2rem;
    }

    @media (min-width: 768px) {
        .order-fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .order-fields {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .order-field dt {
        margin-bottom: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: rgb(107 114 128);
    }

    .dark .order-field dt {
        color: rgb(156 163 175);
    }

    .order-field dd {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .dark .order-field dd {
        color: rgb(243 244 246);
    }

    .order-link {
        color: rgb(180 83 9);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .dark .order-link {
        color: rgb(251 191 36);
    }

    .order-note {
        margin-top: 1rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(254 240 138);
        background: rgb(254 252 232);
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        color: rgb(113 63 18);
    }

    .dark .order-note {
        border-color: rgb(120 53 15);
        background: rgb(66 32 6 / 0.35);
        color: rgb(253 230 138);
    }

    .order-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .order-table th,
    .order-table td {
        padding: 0.75rem 1rem;
        text-align: left;
        vertical-align: middle;
    }

    .order-table th {
        border-bottom: 1px solid rgb(229 231 235);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        background: rgb(249 250 251);
    }

    .dark .order-table th {
        border-bottom-color: rgb(55 65 81);
        color: rgb(156 163 175);
        background: rgb(31 41 55);
    }

    .order-table tbody tr + tr td {
        border-top: 1px solid rgb(243 244 246);
    }

    .dark .order-table tbody tr + tr td {
        border-top-color: rgb(55 65 81);
    }

    .order-table .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .order-summary {
        margin-left: auto;
        width: 100%;
        max-width: 28rem;
    }

    .order-summary__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.5rem 0;
        font-size: 0.875rem;
    }

    .order-summary__row dt {
        color: rgb(107 114 128);
    }

    .dark .order-summary__row dt {
        color: rgb(156 163 175);
    }

    .order-summary__row dd {
        margin: 0;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: rgb(17 24 39);
    }

    .dark .order-summary__row dd {
        color: rgb(243 244 246);
    }

    .order-summary__total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 0.75rem;
        padding-top: 0.875rem;
        border-top: 2px solid rgb(229 231 235);
    }

    .dark .order-summary__total {
        border-top-color: rgb(75 85 99);
    }

    .order-summary__total span:first-child {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .dark .order-summary__total span:first-child {
        color: rgb(243 244 246);
    }

    .order-summary__total span:last-child {
        font-size: 1.375rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: rgb(180 83 9);
    }

    .dark .order-summary__total span:last-child {
        color: rgb(251 191 36);
    }
</style>

<div class="order-view">
    {{-- Status --}}
    <div class="order-status-hero {{ $statusHeroClass }}">
        <div class="order-status-hero__main">
            <span class="order-status-hero__label">Status narudžbe</span>
            <span class="order-status-hero__value">{{ $statusLabel }}</span>
        </div>
        <div class="order-status-hero__meta">
            @if($latestStatusChange)
                <div>
                    Zadnja promjena:
                    <strong>{{ $latestStatusChange->created_at?->format('d.m.Y H:i') }}</strong>
                    · {{ $latestStatusChange->changedByUser?->name ?? 'Sistem' }}
                </div>
                @if(filled($latestStatusChange->note))
                    <span class="order-status-hero__hint">{{ $latestStatusChange->note }}</span>
                @endif
            @else
                <div>
                    Narudžba kreirana:
                    <strong>{{ $order->created_at?->format('d.m.Y H:i') }}</strong>
                </div>
                <span class="order-status-hero__hint">Status se može promijeniti gore desno — „Status narudžbe“ ili „Promijeni status“.</span>
            @endif
        </div>
        <div class="order-meta-total">
            <span class="order-meta-total__label">Ukupno</span>
            <span class="order-meta-total__value">{{ $money($order->total) }}</span>
        </div>
    </div>

    {{-- Meta --}}
    <div class="order-meta-bar">
        <div class="order-meta-items">
            <span>{{ $order->created_at?->format('d.m.Y H:i') }}</span>
            <span>{{ $paymentLabels[$order->payment_method] ?? $order->payment_method ?? '—' }}</span>
            <span>{{ $shippingLabels[$order->shipping_method] ?? $order->shipping_method ?? '—' }}</span>
            <span>{{ $order->items_count }} stavki</span>
        </div>
    </div>

    {{-- 1. Kupac --}}
    <section class="order-panel">
        <div class="order-panel__head">
            <h2 class="order-panel__title">Kupac</h2>
        </div>
        <div class="order-panel__body">
            <dl class="order-fields">
                <div class="order-field">
                    <dt>Ime i prezime</dt>
                    <dd>{{ $order->first_name }} {{ $order->last_name }}</dd>
                </div>
                <div class="order-field">
                    <dt>Telefon</dt>
                    <dd>{{ $order->phone ?: '—' }}</dd>
                </div>
                <div class="order-field">
                    <dt>E-mail</dt>
                    <dd>{{ $order->email ?: '—' }}</dd>
                </div>
                <div class="order-field">
                    <dt>Adresa</dt>
                    <dd>{{ $order->address ?: '—' }}</dd>
                </div>
                <div class="order-field">
                    <dt>Grad / Poštanski broj</dt>
                    <dd>{{ $order->postal_code }} {{ $order->city }}</dd>
                </div>
                @if(filled($order->company_name))
                    <div class="order-field">
                        <dt>Firma</dt>
                        <dd>{{ $order->company_name }}</dd>
                    </div>
                @endif
                @if(filled($order->jib))
                    <div class="order-field">
                        <dt>JIB</dt>
                        <dd>{{ $order->jib }}</dd>
                    </div>
                @endif
                @if(filled($order->pdv_number))
                    <div class="order-field">
                        <dt>PDV broj</dt>
                        <dd>{{ $order->pdv_number }}</dd>
                    </div>
                @endif
                @if($trackingUrl)
                    <div class="order-field">
                        <dt>Praćenje narudžbe</dt>
                        <dd>
                            <a href="{{ $trackingUrl }}" target="_blank" rel="noopener" class="order-link">Otvori tracking link</a>
                        </dd>
                    </div>
                @endif
            </dl>

            @if(filled($order->notes))
                <div class="order-note">
                    <strong>Napomena kupca:</strong> {{ $order->notes }}
                </div>
            @endif
        </div>
    </section>

    {{-- 2. Stavke --}}
    <section class="order-panel">
        <div class="order-panel__head">
            <h2 class="order-panel__title">Stavke narudžbe</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Proizvod</th>
                        <th>Šifra</th>
                        <th>Brend</th>
                        <th class="num">Količina</th>
                        <th class="num">Cijena</th>
                        <th class="num">Ukupno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $index => $item)
                        <tr>
                            <td class="num">{{ $index + 1 }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->displayCode() ?: '—' }}</td>
                            <td>{{ $item->brand_name ?: '—' }}</td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">{{ $money($item->final_price) }}</td>
                            <td class="num"><strong>{{ $money($item->line_total) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: rgb(107 114 128);">Nema stavki.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 3. Sažetak --}}
    <section class="order-panel">
        <div class="order-panel__head">
            <h2 class="order-panel__title">Sažetak</h2>
        </div>
        <div class="order-panel__body">
            <dl class="order-summary">
                <div class="order-summary__row">
                    <dt>Međuzbir</dt>
                    <dd>{{ $money($order->subtotal) }}</dd>
                </div>
                <div class="order-summary__row">
                    <dt>Popust</dt>
                    <dd>{{ $money($order->discount_total) }}</dd>
                </div>
                <div class="order-summary__row">
                    <dt>Dostava</dt>
                    <dd>{{ $money($order->shipping_fee) }}</dd>
                </div>
                @if((float) $order->loyalty_discount_amount > 0)
                    <div class="order-summary__row">
                        <dt>Loyalty popust</dt>
                        <dd>{{ $money($order->loyalty_discount_amount) }}</dd>
                    </div>
                @endif
                @if($order->coupon)
                    <div class="order-summary__row">
                        <dt>Kupon</dt>
                        <dd>{{ $order->coupon->code }}</dd>
                    </div>
                @endif
                @if($order->loyaltyReward)
                    <div class="order-summary__row">
                        <dt>Loyalty nagrada</dt>
                        <dd>{{ $order->loyaltyReward->name }}</dd>
                    </div>
                @endif
                @if((int) $order->points_redeemed > 0)
                    <div class="order-summary__row">
                        <dt>Iskorišteni bodovi</dt>
                        <dd>{{ $order->points_redeemed }}</dd>
                    </div>
                @endif
                @if((int) $order->points_earned > 0)
                    <div class="order-summary__row">
                        <dt>Zarađeni bodovi</dt>
                        <dd>{{ $order->points_earned }}</dd>
                    </div>
                @endif
                <div class="order-summary__total">
                    <span>Za naplatu</span>
                    <span>{{ $money($order->total) }}</span>
                </div>
            </dl>
        </div>
    </section>

    @if($order->statusHistory->isNotEmpty())
        <section class="order-panel">
            <div class="order-panel__head">
                <h2 class="order-panel__title">Historija statusa</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="order-table">
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
                                <td style="white-space: nowrap;">{{ $history->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    {{ OrderStatus::label((string) $history->old_status) ?: '—' }}
                                    →
                                    <strong>{{ OrderStatus::label((string) $history->new_status) }}</strong>
                                </td>
                                <td>{{ $history->changedByUser?->name ?? 'Sistem' }}</td>
                                <td>{{ $history->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
