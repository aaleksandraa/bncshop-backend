<?php

return [
    'token_expiry_days' => (int) env('B2B_TOKEN_EXPIRY_DAYS', 30),

    'shipping' => [
        'fixed_fee' => (float) env('B2B_SHIPPING_FEE', 10),
        'free_threshold' => env('B2B_SHIPPING_FREE_THRESHOLD') !== null
            ? (float) env('B2B_SHIPPING_FREE_THRESHOLD')
            : 500.0,
    ],

    'password_reset_hours' => (int) env('B2B_PASSWORD_RESET_HOURS', 24),

    'new_product_digest_minutes' => (int) env('B2B_NEW_PRODUCT_DIGEST_MINUTES', 5),
];
