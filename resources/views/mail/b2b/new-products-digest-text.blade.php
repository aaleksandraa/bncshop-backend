Poštovani {{ $customerName }},

U B2B katalog su dodani novi proizvodi:

@foreach ($productLines as $line)
- {{ $line }}
@endforeach

Pregled kataloga: {{ $catalogUrl }}

{{ config('app.name') }}
