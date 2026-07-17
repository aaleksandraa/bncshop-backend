<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>Upit za kupovinu na rate</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Novi upit za kupovinu na rate</h2>
    <p style="margin-top: 0;">Primljen je novi upit putem web shopa.</p>

    <h3>Kupac</h3>
    <ul>
        <li><strong>Ime:</strong> {{ $inquiry->full_name }}</li>
        <li><strong>Telefon:</strong> {{ $inquiry->phone }}</li>
        <li><strong>Email:</strong> {{ $inquiry->email }}</li>
    </ul>

    <h3>Proizvod</h3>
    <ul>
        <li><strong>Naziv:</strong> {{ $inquiry->product_name ?? '—' }}</li>
        <li><strong>Slug:</strong> {{ $inquiry->product_slug ?? '—' }}</li>
        <li><strong>Količina:</strong> {{ $inquiry->quantity }}</li>
        <li><strong>Bazna cijena:</strong> {{ number_format((float) $inquiry->base_price, 2, ',', '.') }} KM</li>
    </ul>

    <h3>Plan otplate</h3>
    <ul>
        <li><strong>Tip:</strong> {{ \App\Models\InstallmentInquiry::typeOptions()[$inquiry->installment_type] ?? $inquiry->installment_type }}</li>
        <li><strong>Broj rata:</strong> {{ $inquiry->months }}</li>
        <li><strong>Mjesečna rata:</strong> {{ number_format((float) $inquiry->monthly_amount, 2, ',', '.') }} KM</li>
        <li><strong>Ukupno:</strong> {{ number_format((float) $inquiry->total_amount, 2, ',', '.') }} KM</li>
        <li><strong>Kamata:</strong> {{ number_format((float) $inquiry->interest_rate * 100, 2, ',', '.') }}%</li>
        <li><strong>Provizija:</strong> {{ number_format((float) $inquiry->provision_rate * 100, 2, ',', '.') }}%</li>
    </ul>

    <p>
        <a href="{{ $adminUrl }}">Otvori upit u admin panelu</a>
    </p>
</body>
</html>
