Poštovani {{ $user->name }},

Vaš zahtjev za pristup B2B portalu je odobren.

Postavite lozinku i pristupite katalogu na linku ispod (vrijedi 72 sata):

{{ $setupUrl }}

Hvala,
{{ config('app.name') }}

Kontakt: {{ config('b2b.mail.from_address', config('mail.from.address')) }}
