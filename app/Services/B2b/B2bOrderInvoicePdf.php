<?php

namespace App\Services\B2b;

use App\Models\B2bOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class B2bOrderInvoicePdf
{
    public function generateAndStore(B2bOrder $order): string
    {
        $order->loadMissing('items');

        $relativePath = 'b2b/invoices/'.$order->order_number.'.pdf';
        $pdf = Pdf::loadView('b2b.order-invoice', ['order' => $order])
            ->setPaper('a4');

        Storage::disk('local')->put($relativePath, $pdf->output());

        $order->update(['invoice_path' => $relativePath]);

        return $relativePath;
    }

    public function download(B2bOrder $order): Response
    {
        $order->loadMissing('items');

        if ($order->invoice_path && Storage::disk('local')->exists($order->invoice_path)) {
            return response()->download(
                Storage::disk('local')->path($order->invoice_path),
                'faktura-'.$order->order_number.'.pdf',
                ['Content-Type' => 'application/pdf'],
            );
        }

        return Pdf::loadView('b2b.order-invoice', ['order' => $order])
            ->setPaper('a4')
            ->download('faktura-'.$order->order_number.'.pdf');
    }
}
