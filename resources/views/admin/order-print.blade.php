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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: #111;
            font-size: 13px;
            line-height: 1.45;
        }
        .toolbar {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .toolbar button {
            padding: 10px 16px;
            cursor: pointer;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            border-radius: 6px;
            font-size: 14px;
        }
        .toolbar .hint { color: #555; font-size: 12px; }
        .order-sheet {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            page-break-inside: avoid;
        }
        .order-sheet + .order-sheet { page-break-before: always; }
        .order-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #111;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .store-name {
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #666;
        }
        h1 {
            margin: 4px 0 0;
            font-size: 24px;
        }
        h2 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .order-meta div { margin-bottom: 4px; }
        .section { margin-bottom: 18px; }
        .grid.two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .field {
            margin-bottom: 8px;
        }
        .field span {
            display: block;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .field strong { font-size: 14px; }
        .field.notes {
            margin-top: 12px;
            padding: 10px 12px;
            background: #f7f7f7;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        table.compact th, table.compact td { font-size: 12px; }
        .totals-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 24px;
            max-width: 520px;
            margin-left: auto;
        }
        .field.total strong { font-size: 18px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .order-sheet {
                border: none;
                border-radius: 0;
                padding: 0;
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Štampaj / PDF</button>
        <span class="hint">
            @if($orders->count() === 1)
                1 narudžba
            @else
                {{ $orders->count() }} narudžbi
            @endif
        </span>
    </div>

    @foreach($orders as $order)
        @include('admin.partials.order-print-sheet', ['order' => $order])
    @endforeach
</body>
</html>
