Novi zahtjev za B2B pristup

Kontakt: {{ $request->fullName() }}
Email: {{ $request->email }}
Telefon: {{ $request->phone }}

Firma: {{ $request->company_name }}
Adresa: {{ $request->company_address }}
JIB: {{ $request->jib }}
@if($request->pdv_number)
PDV broj: {{ $request->pdv_number }}
@endif

Pregledaj zahtjev u B2B adminu:
{{ rtrim((string) config('app.url'), '/').'/b2b-admin/b2b-access-requests' }}

{{ config('app.name') }}
