<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>BNC kartica {{ $card->card_number }}</title>
    <style>
        @page { size: 85.6mm 53.98mm; margin: 0; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 16px;
            color: #111;
        }
        .card {
            width: 320px;
            border: 2px solid #111;
            border-radius: 12px;
            padding: 20px;
        }
        .brand { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #666; }
        .title { font-size: 20px; font-weight: bold; margin: 8px 0 16px; }
        .number { font-size: 24px; font-weight: bold; letter-spacing: 1px; margin: 12px 0; }
        .name { font-size: 14px; margin-bottom: 8px; }
        .barcode {
            font-family: 'Libre Barcode 128', monospace;
            font-size: 42px;
            line-height: 1;
            margin-top: 12px;
        }
        .hint { font-size: 11px; color: #555; margin-top: 12px; line-height: 1.4; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="padding: 10px 16px; cursor: pointer;">Štampaj / PDF</button>
    </div>

    <div class="card">
        <div class="brand">BNC Shop</div>
        <div class="title">Loyalty kartica</div>
        <div class="name">{{ $customer->user?->name }}</div>
        <div class="number">{{ $card->card_number }}</div>
        <div class="barcode">{{ $card->card_number }}</div>
        <div class="hint">
            Pokažite karticu u radnji pri kupovini. Bodovi i nagrade su dostupni i na web shopu pod istim računom.
        </div>
    </div>
</body>
</html>
