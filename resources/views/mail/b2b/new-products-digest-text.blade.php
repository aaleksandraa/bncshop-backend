Poštovani {{ $customerName }},

@if (filled($customIntro))
{{ $customIntro }}

@endif
{{ config('b2b.product_notification.predefined_intro', 'U B2B katalog su dodani novi proizvodi:') }}

@foreach ($products as $product)
• {!! $product['name'] !!}@if ($product['sku']) ({!! $product['sku'] !!})@endif
  Cijena: {!! $product['price'] !!} KM
  Pogledajte: {!! $product['url'] !!}

@endforeach
Cijene su prikazane sa vašim B2B popustom.

Pregled cijelog kataloga: {{ $catalogUrl }}

Srdačan pozdrav,
{{ config('b2b.mail.from_name', 'BNC B2B') }}
{{ config('b2b.mail.from_address', config('mail.from.address')) }}
