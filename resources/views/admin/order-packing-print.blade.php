<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="utf-8">
    <title>
        @if($single && $orders->count() === 1)
            Pakovanje {{ $orders->first()->order_number }}
        @else
            Pakovanje ({{ $orders->count() }})
        @endif
    </title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 16px;
            color: #111;
            font-size: 13px;
            line-height: 1.4;
        }
        .toolbar {
            margin-bottom: 16px;
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
        }
        .packing-sheet {
            border: 2px solid #111;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .packing-sheet + .packing-sheet { page-break-before: always; }
        .packing-header {
            margin-bottom: 16px;
            border-bottom: 2px dashed #999;
            padding-bottom: 12px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .packing-header-meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.6;
        }
        .meta-label {
            display: inline-block;
            min-width: 72px;
            color: #666;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.4px;
        }
        .store-name { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .order-number { font-size: 28px; font-weight: bold; margin-top: 4px; }
        .order-date { color: #555; margin-top: 4px; }
        h2 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .address-block { margin-bottom: 16px; }
        .customer-name { font-size: 18px; font-weight: bold; margin: 0 0 6px; }
        .address-line, .contact-line { margin: 0 0 4px; }
        .notes-block {
            margin-bottom: 16px;
            padding: 10px 12px;
            background: #fff8e1;
            border: 1px solid #f0d060;
            border-radius: 6px;
        }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td {
            border: 1px solid #ccc;
            padding: 8px;
            vertical-align: top;
        }
        .items-table th {
            background: #f3f3f3;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .col-index { width: 28px; text-align: center; }
        .col-qty, .col-check { text-align: center; }
        .col-qty { width: 48px; }
        .col-check { width: 32px; }
        .col-code { width: 120px; }
        .col-price, .col-total { width: 88px; text-align: right; white-space: nowrap; }
        .product-name { display: block; font-weight: 600; }
        .product-brand {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            color: #666;
        }
        .product-code {
            display: block;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .muted { color: #999; }
        .summary-block {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
        .summary-box {
            min-width: 280px;
            border: 1px solid #ccc;
            border-radius: 6px;
            overflow: hidden;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 8px 12px;
            border-bottom: 1px solid #e5e5e5;
            font-size: 12px;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-total {
            background: #111;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .packing-sheet { border-width: 1px; margin-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Štampaj / PDF</button>
        <span>{{ $orders->count() }} {{ $orders->count() === 1 ? 'narudžba' : 'narudžbi' }}</span>
    </div>

    @foreach($orders as $order)
        @include('admin.partials.order-packing-sheet', ['order' => $order])
    @endforeach
</body>
</html>
