<?php

namespace App\Services\B2b;

use App\Models\B2bOrder;
use App\Support\B2bInvoiceVat;
use App\Support\B2bPaymentMethod;
use App\Support\B2bQuoteDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class B2bOrderInvoicePdf
{
    public function generateAndStore(B2bOrder $order): string
    {
        $order->loadMissing('items');

        $relativePath = 'b2b/invoices/'.$order->order_number.'.pdf';
        $pdf = $this->renderPdf($order);

        Storage::disk('local')->put($relativePath, $pdf->output());

        $order->update(['invoice_path' => $relativePath]);

        return $relativePath;
    }

    public function download(B2bOrder $order): Response
    {
        $order->loadMissing('items');

        return $this->renderPdf($order)
            ->download(B2bQuoteDocument::downloadFilename($order->order_number));
    }

    private function renderPdf(B2bOrder $order): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('b2b.order-invoice', [
            'order' => $order,
            'vat' => B2bInvoiceVat::forOrder($order),
            'documentTitle' => B2bQuoteDocument::title(),
            'paymentMethodLabel' => B2bPaymentMethod::label($order->payment_method),
            'logoDataUri' => B2bQuoteDocument::logoDataUri(),
        ])->setPaper('a4');
    }
}
