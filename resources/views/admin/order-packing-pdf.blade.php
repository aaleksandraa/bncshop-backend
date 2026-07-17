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
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
            font-size: 12px;
            line-height: 1.4;
        }
        .packing-sheet { page-break-after: always; border: 1px solid #111; padding: 14px; }
        .packing-sheet:last-child { page-break-after: auto; }
        .packing-header {
            border-bottom: 1px dashed #999;
            padding-bottom: 10px;
            margin-bottom: 12px;
            display: table;
            width: 100%;
        }
        .packing-header-main, .packing-header-meta { display: table-cell; vertical-align: top; }
        .packing-header-meta { text-align: right; font-size: 10px; line-height: 1.5; }
        .meta-label { color: #666; text-transform: uppercase; font-size: 8px; }
        .store-name { font-size: 9px; text-transform: uppercase; color: #666; }
        .order-number { font-size: 22px; font-weight: bold; }
        h2 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; }
        .customer-name { font-size: 16px; font-weight: bold; margin: 0 0 4px; }
        p { margin: 0 0 3px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items-table th, .items-table td { border: 1px solid #ccc; padding: 5px 6px; vertical-align: top; }
        .items-table th { background: #f3f3f3; font-size: 8px; text-transform: uppercase; }
        .col-index, .col-qty, .col-check { text-align: center; }
        .col-price, .col-total { text-align: right; white-space: nowrap; }
        .product-name { font-weight: bold; }
        .product-brand { display: block; font-size: 9px; color: #666; }
        .product-code { font-family: DejaVu Sans Mono, monospace; font-size: 10px; font-weight: bold; }
        .muted { color: #999; }
        .summary-block { margin-top: 10px; text-align: right; }
        .summary-box {
            display: inline-block;
            min-width: 220px;
            border: 1px solid #ccc;
        }
        .summary-row {
            display: table;
            width: 100%;
            border-bottom: 1px solid #e5e5e5;
            font-size: 10px;
        }
        .summary-row span { display: table-cell; padding: 5px 8px; }
        .summary-row span:last-child { text-align: right; font-weight: bold; }
        .summary-total { background: #111; color: #fff; font-size: 11px; }
        .notes-block { background: #fff8e1; border: 1px solid #f0d060; padding: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>
    @foreach($orders as $order)
        @include('admin.partials.order-packing-sheet', ['order' => $order])
    @endforeach
</body>
</html>
