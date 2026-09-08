<?php

return [
    'currency' => env('BNC_CURRENCY', 'BAM'),
    'currency_symbol' => env('BNC_CURRENCY_SYMBOL', 'KM'),

    /** Gross VAT rate applied only when deriving a price from wholesale + margin (no API price). */
    'vat_rate_percent' => (float) env('BNC_VAT_RATE_PERCENT', 17),

    'attribute_type_map' => [
        0 => 'text',
        1 => 'number',
        2 => 'boolean',
    ],

    'rebate_type_map' => [],

    'discount_combination_mode' => env('BNC_DISCOUNT_MODE', 'best_single'),
    'coupon_combines_with_sale' => env('BNC_COUPON_COMBINES', false),
    'shipping_multi_category_mode' => env('BNC_SHIPPING_MULTI', 'max'),

    'new_product_days' => 30,
    'default_page_size' => 100,

    'a1_api_verify_ssl' => env('A1_API_VERIFY_SSL', true),
    'a1_api_timeout' => (int) env('A1_API_TIMEOUT', 120),
    'a1_api_retries' => (int) env('A1_API_RETRIES', 5),
    'a1_api_retry_delay_ms' => (int) env('A1_API_RETRY_DELAY_MS', 10000),
    'a1_api_max_page_size' => (int) env('A1_API_MAX_PAGE_SIZE', 50),
    'a1_api_incremental_page_size' => (int) env('A1_API_INCREMENTAL_PAGE_SIZE', 25),
    'a1_api_page_delay_ms' => (int) env('A1_API_PAGE_DELAY_MS', 1000),
    'a1_sync_failure_cooldown_minutes' => (int) env('A1_SYNC_FAILURE_COOLDOWN_MINUTES', 30),
    'a1_sync_pages_per_job' => (int) env('A1_SYNC_PAGES_PER_JOB', 40),
    'a1_sync_job_time_budget_seconds' => (int) env('A1_SYNC_JOB_TIME_BUDGET_SECONDS', 5400),
    'a1_sync_job_timeout' => (int) env('A1_SYNC_JOB_TIMEOUT', 10800),
    'a1_sync_stale_idle_minutes' => (int) env('A1_SYNC_STALE_IDLE_MINUTES', 45),

    'a1_api_base_url' => env('A1_API_BASE_URL', 'https://a1team.ba'),
    'a1_api_username' => env('A1_API_USERNAME', 'bnc'),
    'a1_api_password' => env('A1_API_PASSWORD'),
    'a1_api_target_system_code' => env('A1_API_TARGET_SYSTEM_CODE', 'bnc-shop'),
    'a1_api_page_size' => (int) env('A1_API_PAGE_SIZE', 50),

    'eline_api_base_url' => env('ELINE_API_BASE_URL', 'https://www8.eline.ba/bl/RestWebShop.svc/json'),
    'eline_api_token' => env('ELINE_API_TOKEN'),
    'eline_api_shop_code' => env('ELINE_API_SHOP_CODE', 'bncshop'),
    'eline_api_timeout' => (int) env('ELINE_API_TIMEOUT', 120),
    'eline_api_retries' => (int) env('ELINE_API_RETRIES', 3),
    'eline_api_verify_ssl' => env('ELINE_API_VERIFY_SSL', env('A1_API_VERIFY_SSL', true)),
    'eline_sync_interval_minutes' => (int) env('ELINE_SYNC_INTERVAL_MINUTES', 720),
    'eline_sync_times' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ELINE_SYNC_TIMES', '06:00,18:00')),
    ))),
    'eline_active_value' => 255,

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'meta_internal_key' => env('META_INTERNAL_KEY'),

    /*
    | Meta Product Catalog CSV feed — category whitelist and name exclusions.
    | Override per environment in admin (Tracking → Meta catalog) or via env lists.
    */
    'meta_catalog' => [
        'default_include_category_slugs' => env('META_CATALOG_INCLUDE_CATEGORY_SLUGS', implode("\n", [
            'it-oprema/racunari',
            'it-oprema/laptopi',
            'it-oprema/periferija/monitori',
            'it-oprema/periferija/misevi',
            'it-oprema/periferija/tastature',
            'klima-grijanje',
            'print-kancelarija/printeri',
        ])),
        'default_exclude_name_keywords' => env('META_CATALOG_EXCLUDE_NAME_KEYWORDS', implode("\n", [
            'zaštitno staklo',
            'zastitno staklo',
            'zaštitna folija',
            'zastitna folija',
            'screen protector',
            'tempered glass',
            'maskica za',
            'kaljeno staklo',
        ])),
        'image_origin' => env('META_CATALOG_IMAGE_ORIGIN', env('BNC_MEDIA_ORIGIN', 'https://images.bnc.ba')),
    ],

    /*
    | Legacy synced /storage/ files may live on a different host than APP_URL
    | (e.g. api.bncshop.ba while admin runs on api.bnc.ba). Used only by
    | PublicStorageUrl — never set Laravel's global asset_url to this value.
    */
    'legacy_storage_url' => env('LEGACY_STORAGE_URL', env('ASSET_URL')),

    /*
    | CDN origin for optimized media (Cloudflare R2 via images.bnc.ba).
    | When empty, PublicStorageUrl keeps legacy split-host behaviour.
    */
    'media_origin' => env('BNC_MEDIA_ORIGIN'),

    'media_disk' => env('BNC_MEDIA_DISK', 'r2'),

    'media_master_max_width' => (int) env('BNC_MEDIA_MASTER_MAX_WIDTH', 1600),

    'media_webp_quality' => (int) env('BNC_MEDIA_WEBP_QUALITY', 82),

    /** @var list<int> Responsive variant widths uploaded alongside the master. */
    'media_variant_widths' => [320, 640, 1280],

    'media_migration_memory_limit' => env('BNC_MEDIA_MIGRATION_MEMORY_LIMIT', '512M'),

    'media_migration_max_source_bytes' => (int) env('BNC_MEDIA_MIGRATION_MAX_SOURCE_BYTES', 20 * 1024 * 1024),

    'seller_notification_email' => env('SELLER_EMAIL', env('ADMIN_EMAIL', env('MAIL_FROM_ADDRESS'))),
    'admin_notification_email' => env('ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),

    'order_statuses' => [
        'nova',
        'u_obradi',
        'potvrđena',
        'spakovano',
        'spremno_za_preuzimanje',
        'poslano',
        'isporučeno',
        'otkazano',
        'vraćeno',
        'neuspjela_dostava',
        'arhivirano',
    ],

    'installments' => [
        'mikrofin_min_credit' => 200.0,
        'mikrofin_max_credit' => 3000.0,
        'mikrofin_max_months' => 36,
        'mikrofin_zero_interest_max_months' => 18,
        'mikrofin_provision_rate' => 0.10,
        'mikrofin_interest_rate' => 0.22,
        'min_installment' => 25.0,
        'card_markup_rate' => 0.10,
        'card_months' => 24,
    ],

    'installment_inquiry_statuses' => [
        'nova',
        'kontaktirana',
        'zatvorena',
    ],

    'olx_api_base_url' => env('OLX_API_BASE_URL', 'https://api.olx.ba'),
    'olx_api_username' => env('OLX_USERNAME', env('OLX_API_USERNAME')),
    'olx_api_password' => env('OLX_PASSWORD', env('OLX_API_PASSWORD')),
    'olx_api_device_name' => env('OLX_DEVICE_NAME', 'bncshopweb_integration'),
    'olx_api_timeout' => (int) env('OLX_API_TIMEOUT', 60),
    'olx_api_retries' => (int) env('OLX_API_RETRIES', 3),
    'olx_api_rate_limit_ms' => (int) env('OLX_API_RATE_LIMIT_MS', 500),
    'olx_image_upload_timeout' => (int) env('OLX_IMAGE_UPLOAD_TIMEOUT', 60),
    'olx_image_download_timeout' => (int) env('OLX_IMAGE_DOWNLOAD_TIMEOUT', 30),
    'olx_max_images_per_listing' => (int) env('OLX_MAX_IMAGES_PER_LISTING', 8),
    'olx_daily_create_limit' => (int) env('OLX_DAILY_CREATE_LIMIT', 350),
    'olx_max_creates_per_run' => (int) env('OLX_MAX_CREATES_PER_RUN', 175),
    'olx_sync_wave_size' => (int) env('OLX_SYNC_WAVE_SIZE', 40),
    'olx_sync_stale_idle_minutes' => (int) env('OLX_SYNC_STALE_IDLE_MINUTES', 90),
    'olx_sync_memory_limit' => env('OLX_SYNC_MEMORY_LIMIT', '512M'),
    'olx_platform_max_images_per_listing' => (int) env('OLX_PLATFORM_MAX_IMAGES_PER_LISTING', 25),
    'olx_image_watermark_enabled' => env('OLX_IMAGE_WATERMARK_ENABLED', true),
    'olx_image_watermark_path' => env('OLX_IMAGE_WATERMARK_PATH', resource_path('olx/bnc-logo.png')),
    'olx_image_watermark_width_ratio' => (float) env('OLX_IMAGE_WATERMARK_WIDTH_RATIO', 0.333333),
    'olx_image_watermark_bottom_offset_ratio' => (float) env('OLX_IMAGE_WATERMARK_BOTTOM_OFFSET_RATIO', 0.2),
    'olx_image_watermark_black_threshold' => (int) env('OLX_IMAGE_WATERMARK_BLACK_THRESHOLD', 24),
    'olx_api_verify_ssl' => env('OLX_API_VERIFY_SSL', true),
    'olx_shop_username' => env('OLX_SHOP_USERNAME', 'bnc'),
    'olx_sync_times' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('OLX_SYNC_TIMES', '06:00,18:00')),
    ))),
    'olx_default_country_id' => (int) env('OLX_DEFAULT_COUNTRY_ID', 49),
    'olx_default_city_id' => (int) env('OLX_DEFAULT_CITY_ID', 133),
    'olx_default_location_lat' => env('OLX_DEFAULT_LOCATION_LAT', '43.85547203690044'),
    'olx_default_location_lon' => env('OLX_DEFAULT_LOCATION_LON', '18.408615515357727'),

    /*
    |--------------------------------------------------------------------------
    | Ananas Merchant API (Phase 1A — read-only client)
    |--------------------------------------------------------------------------
    */
    'ananas_env' => env('ANANAS_ENV', 'stage'),
    'ananas_client_id' => env('ANANAS_CLIENT_ID'),
    'ananas_client_secret' => env('ANANAS_CLIENT_SECRET'),
    'ananas_allow_catalog_writes' => filter_var(env('ANANAS_ALLOW_CATALOG_WRITES', false), FILTER_VALIDATE_BOOL),
    'ananas_api_timeout' => (int) env('ANANAS_API_TIMEOUT', 60),
    'ananas_api_retries' => (int) env('ANANAS_API_RETRIES', 3),
    'ananas_api_verify_ssl' => env('ANANAS_API_VERIFY_SSL', true),
    'ananas_token_cache_safety_seconds' => (int) env('ANANAS_TOKEN_CACHE_SAFETY_SECONDS', 120),
    'ananas_api_max_429_retries' => (int) env('ANANAS_API_MAX_429_RETRIES', 3),
    'ananas_429_backoff_base_ms' => (int) env('ANANAS_429_BACKOFF_BASE_MS', 1000),
    'ananas_429_backoff_jitter_ms' => (int) env('ANANAS_429_BACKOFF_JITTER_MS', 250),
    'ananas_rate_limit_products_rps' => (int) env('ANANAS_RATE_LIMIT_PRODUCTS_RPS', 5),
    'ananas_rate_limit_products_rpm' => (int) env('ANANAS_RATE_LIMIT_PRODUCTS_RPM', 60),
    'ananas_rate_limit_warehouses_rps' => (int) env('ANANAS_RATE_LIMIT_WAREHOUSES_RPS', 5),
    'ananas_rate_limit_warehouses_rpm' => (int) env('ANANAS_RATE_LIMIT_WAREHOUSES_RPM', 300),
    'ananas_stage_iam_base_url' => env('ANANAS_STAGE_IAM_BASE_URL', 'https://api.qa2.ananastest.com'),
    'ananas_stage_token_url' => env('ANANAS_STAGE_TOKEN_URL', 'https://api.qa2.ananastest.com/iam/api/v1/auth/token'),
    'ananas_stage_product_base_url' => env('ANANAS_STAGE_PRODUCT_BASE_URL', 'https://api.qa2.ananastest.com'),
    'ananas_stage_svc_base_url' => env('ANANAS_STAGE_SVC_BASE_URL', 'https://api.svc.qa2.ananastest.com'),
    'ananas_production_iam_base_url' => env('ANANAS_PRODUCTION_IAM_BASE_URL', 'https://api.ananas.rs'),
    'ananas_production_token_url' => env('ANANAS_PRODUCTION_TOKEN_URL', 'https://api.ananas.rs/iam/api/v1/auth/token'),
    'ananas_production_product_base_url' => env('ANANAS_PRODUCTION_PRODUCT_BASE_URL', 'https://api.ananas.rs'),
    'ananas_production_svc_base_url' => env('ANANAS_PRODUCTION_SVC_BASE_URL', 'https://api.svc.ananas.rs'),

    /*
    | BiH VAT for Ananas payload: must be 0, 10, or 20 once confirmed by Ananas.
    | Leave null until Ananas answers — eligibility returns VAT_UNRESOLVED.
    */
    'ananas_vat_rate' => ($rate = env('ANANAS_VAT_RATE')) !== null && $rate !== '' ? (int) $rate : null,

    /*
    | Approved package-weight attribute name chain (first non-empty raw_value wins).
    */
    'ananas_weight_attribute_names' => [
        'Bruto težina pakovanja',
        'Bruto težina kutije',
        'Težina Paketa',
        'Težina pakovanja',
        'Bruto težina',
        'Težina',
        'Neto težina',
    ],

    'product_image_download_timeout' => (int) env('BNC_PRODUCT_IMAGE_DOWNLOAD_TIMEOUT', 30),
    'product_image_verify_ssl' => env('BNC_PRODUCT_IMAGE_VERIFY_SSL', env('A1_API_VERIFY_SSL', true)),
    'product_image_download_on_import' => env('BNC_PRODUCT_IMAGE_DOWNLOAD_ON_IMPORT', true),
    // When true, API serves images.bnc.ba from local_path without an R2 HEAD on every request.
    'trust_local_image_path' => env('BNC_TRUST_LOCAL_IMAGE_PATH', true),
    'resolved_image_url_cache_ttl' => (int) env('BNC_RESOLVED_IMAGE_URL_CACHE_TTL', 3600),
];
