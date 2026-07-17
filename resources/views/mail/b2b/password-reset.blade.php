<x-mail::message>
# Resetovanje lozinke

Poštovani {{ $user->name }},

Primili smo zahtjev za resetovanje lozinke za vaš B2B račun. Kliknite na dugme ispod da postavite novu lozinku.

<x-mail::button :url="$resetUrl">
Resetuj lozinku
</x-mail::button>

Link vrijedi {{ config('b2b.password_reset_hours', 24) }} sata.

Ako niste zatražili resetovanje lozinke, ignorišite ovaj email.

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
