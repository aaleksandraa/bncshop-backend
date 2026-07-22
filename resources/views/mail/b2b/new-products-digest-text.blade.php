Poštovani {{ $customerName }},

@if (filled($customIntro))
{{ $customIntro }}

@endif
{{ config('b2b.product_notification.predefined_intro', 'U B2B katalog su dodani novi proizvodi:') }}

@foreach ($productLines as $line)
- {{ $line }}
@endforeach

Pregled kataloga: {{ $catalogUrl }}

{{ config('app.name') }}
