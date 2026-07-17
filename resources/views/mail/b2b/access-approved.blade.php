<x-mail::message>
# B2B pristup odobren

Poštovani {{ $user->name }},

Vaš zahtjev za pristup B2B portalu je odobren. Kliknite na dugme ispod da postavite lozinku i pristupite katalogu.

<x-mail::button :url="$setupUrl">
Postavi lozinku
</x-mail::button>

Link vrijedi 72 sata.

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
