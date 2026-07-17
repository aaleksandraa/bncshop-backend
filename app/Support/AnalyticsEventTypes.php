<?php

namespace App\Support;

class AnalyticsEventTypes
{
    /** @var list<string> */
    public const ALLOWED = [
        'page_view',
        'product_view',
        'add_to_cart',
        'buy_now',
        'remove_from_cart',
        'search',
        'checkout_start',
        'checkout_complete',
        'category_view',
    ];
}
