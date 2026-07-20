<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesB2bAdminAccess;
use App\Http\Controllers\Controller;
use App\Models\B2bOrder;
use App\Services\B2b\B2bOrderInvoicePdf;
use Illuminate\Http\Response;

class B2bOrderInvoiceController extends Controller
{
    use AuthorizesB2bAdminAccess;

    public function __construct(
        private readonly B2bOrderInvoicePdf $invoicePdf,
    ) {}

    public function __invoke(B2bOrder $order): Response
    {
        $this->authorizeB2bAdminAccess();

        return $this->invoicePdf->download($order);
    }
}
