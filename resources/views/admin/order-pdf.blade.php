<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>
        @if($single && $orders->count() === 1)
            Narudžba {{ $orders->first()->order_number }}
        @else
            Narudžbe ({{ $orders->count() }})
        @endif
    </title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
            font-size: 11px;
            line-height: 1.4;
        }
        .order-sheet {
            page-break-after: always;
        }
        .order-sheet:last-child { page-break-after: auto; }
        .order-header {
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .store-name {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
        }
        h1 { margin: 4px 0 0; font-size: 18px; }
        h2 {
            margin: 0 0 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .section { margin-bottom: 14px; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; padding-right: 12px; }
        .field { margin-bottom: 6px; }
        .field-label {
            display: block;
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td {
            border: 1px solid #ccc;
            padding: 6px 8px;
        }
        table.items th { background: #f3f3f3; }
        .num { text-align: right; }
        .totals { width: 280px; margin-left: auto; }
        .totals .field { display: table; width: 100%; }
        .totals .field-label, .totals .field-value { display: table-cell; }
        .totals .field-value { text-align: right; font-weight: bold; }
        .total-row .field-value { font-size: 14px; }
    </style>
</head>
<body>
    @foreach($orders as $order)
        @include('admin.partials.order-pdf-sheet', ['order' => $order])
    @endforeach
</body>
</html>
