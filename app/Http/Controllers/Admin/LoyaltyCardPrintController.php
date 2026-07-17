<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyCard;
use Illuminate\View\View;

class LoyaltyCardPrintController extends Controller
{
    public function __invoke(LoyaltyCard $loyaltyCard): View
    {
        abort_unless(auth()->check(), 403);

        $loyaltyCard->load('customer.user');

        return view('loyalty.card-print', [
            'card' => $loyaltyCard,
            'customer' => $loyaltyCard->customer,
        ]);
    }
}
