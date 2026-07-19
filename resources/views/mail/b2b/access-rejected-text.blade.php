Poštovani {{ $accessRequest->fullName() }},

Nažalost, vaš zahtjev za pristup B2B portalu za firmu {{ $accessRequest->company_name }} nije odobren u ovom trenutku.

Ako smatrate da je došlo do greške ili želite dodatne informacije, kontaktirajte nas putem kontakt forme na web shopu.

Hvala na razumijevanju,
{{ config('app.name') }}

Kontakt: {{ config('b2b.mail.from_address', config('mail.from.address')) }}
