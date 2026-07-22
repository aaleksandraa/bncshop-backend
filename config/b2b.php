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

    'product_notification' => [
        'default_custom_intro' => 'Obavještavamo vas o novim artiklima u našoj B2B ponudi.',
        'predefined_intro' => 'U B2B katalog su dodani novi proizvodi:',
        'new_product_days' => (int) env('B2B_NOTIFICATION_NEW_PRODUCT_DAYS', 30),
    ],

    'mail' => [
        'from_address' => env('B2B_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('B2B_MAIL_FROM_NAME', 'BNC B2B'),
        'admin_notification_email' => env('B2B_ADMIN_NOTIFICATION_EMAIL', 'b2b@bncshop.ba'),
        // Send through the authorized MAIL_FROM transport (info@) to avoid Postfix rejecting b2b@ as envelope sender.
        'use_global_from' => filter_var(env('B2B_MAIL_USE_GLOBAL_FROM', true), FILTER_VALIDATE_BOOL),
    ],

    'vat_rate_percent' => (float) env('B2B_VAT_RATE_PERCENT', 17),

    'invoice' => [
        'seller_jib' => env('B2B_INVOICE_SELLER_JIB'),
        'seller_pdv' => env('B2B_INVOICE_SELLER_PDV'),
    ],
];
