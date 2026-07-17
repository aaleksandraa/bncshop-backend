<x-mail::message>
# Zahtjev za B2B pristup

Poštovani {{ $accessRequest->fullName() }},

Nažalost, vaš zahtjev za pristup B2B portalu za firmu **{{ $accessRequest->company_name }}** nije odobren u ovom trenutku.

Ako smatrate da je došlo do greške ili želite dodatne informacije, kontaktirajte nas putem kontakt forme na web shopu.

Hvala na razumijevanju,<br>
{{ config('app.name') }}
</x-mail::message>
