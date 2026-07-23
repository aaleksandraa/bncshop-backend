<?php

namespace Database\Seeders;

use App\Models\ShippingRule;
use App\Models\SystemSetting;
use App\Services\Commerce\InstallmentSettings;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'shop_name' => [
                'value' => ['name' => 'BNC Shop'],
                'group' => 'shop',
            ],
            'shop_contact' => [
                'value' => [
                    'email' => 'prodaja@bnc.ba',
                    'email_info' => 'info@bnc.ba',
                    'phone' => '+387 61 891 148',
                    'mobile' => '+387 61 891 148',
                    'address' => 'Merhemića trg 2, 71000 Sarajevo, Bosna i Hercegovina',
                    'website' => 'https://bnc.ba',
                    'facebook_url' => 'https://www.facebook.com/Racunari.BNC/',
                    'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=Merhemi%C4%87a+trg+2,+71000+Sarajevo',
                    'maps_embed_url' => 'https://maps.google.com/maps?q=Merhemi%C4%87a+trg+2,+71000+Sarajevo,+Bosna+i+Hercegovina&hl=bs&z=17&output=embed',
                ],
                'group' => 'shop',
            ],
            'currency' => [
                'value' => [
                    'code' => config('bnc.currency'),
                    'symbol' => config('bnc.currency_symbol'),
                ],
                'group' => 'shop',
            ],
            'product_page' => [
                'value' => [
                    'show_short_description' => true,
                    'show_messaging_order_buttons' => false,
                ],
                'group' => 'shop',
            ],
            'catalog_listing' => [
                'value' => [
                    'hide_out_of_stock_refurbished_eline' => true,
                ],
                'group' => 'shop',
            ],
            'checkout' => [
                'value' => [
                    'payment_methods' => ['pay_on_delivery', 'bank_transfer'],
                    'shipping_methods' => ['delivery', 'pickup'],
                    'guest_checkout_enabled' => true,
                    'guest_registration_prompt_checkout' => true,
                    'terms_page_slug' => 'uslovi',
                    'privacy_page_slug' => 'privatnost',
                    'terms_default_checked' => true,
                ],
                'group' => 'checkout',
            ],
            'loyalty' => [
                'value' => [
                    'enabled' => false,
                    'program_name' => 'BNC bodovi',
                    'program_description' => 'Skupljajte bodove za svaku isporučenu narudžbu i iskoristite nagrade.',
                    'starts_at' => null,
                    'ends_at' => null,
                    'points_per_km' => 1,
                    'combine_with_coupons' => false,
                    'combine_with_discounts' => true,
                    'expiry_mode' => 'never',
                    'expiry_months' => 12,
                    'guest_registration_prompt' => true,
                ],
                'group' => 'loyalty',
            ],
            'seo' => [
                'value' => [
                    'default_title_suffix' => ' | BNC Webshop',
                    'robots' => 'index,follow',
                ],
                'group' => 'seo',
            ],
            'homepage_weekly_offer' => [
                'value' => [
                    'enabled' => true,
                    'title' => 'Ponuda sedmice',
                    'subtitle' => null,
                    'layout' => 'spotlight_card',
                    'product_limit' => 1,
                    'product_ids' => [],
                ],
                'group' => 'homepage',
            ],
            'homepage_category_chips' => [
                'value' => [
                    'enabled' => true,
                    'title' => 'Šta danas tražite?',
                    'subtitle' => 'Odaberite kategoriju — jednostavno i brzo.',
                    'category_limit' => 6,
                    'category_ids' => [],
                ],
                'group' => 'homepage',
            ],
            'brevo' => [
                'value' => [
                    'enabled' => false,
                    'api_key' => '',
                    'sender_email' => '',
                    'sender_name' => 'BNC Shop',
                    'default_list_id' => null,
                    'sync_on_order' => true,
                    'sync_registered' => true,
                ],
                'group' => 'integrations',
            ],
            'partner_export' => [
                'value' => [
                    'enabled' => false,
                    'partner_name' => '',
                    'api_key_hash' => null,
                    'api_key_hint' => null,
                    'api_key_created_at' => null,
                    'require_https' => true,
                    'require_ip_allowlist' => true,
                    'allowed_ips' => [],
                    'rate_limit_per_minute' => 60,
                    'max_failed_auth_per_minute' => 10,
                    'log_access' => true,
                    'last_used_at' => null,
                    'last_used_ip' => null,
                ],
                'group' => 'integrations',
            ],
            'tracking' => [
                'value' => [
                    'consent_enabled' => true,
                    'consent_title' => 'Kolačići i privatnost',
                    'consent_message' => 'Koristimo kolačiće kako bismo poboljšali vaše iskustvo, analizirali promet i prikazali relevantne ponude. Po defaultu su uključeni analitički i marketing kolačići; možete ih odbiti ili prilagoditi u postavkama kolačića.',
                    'privacy_page_slug' => 'privatnost',
                    'ga_measurement_id' => '',
                    'fb_pixel_id' => '',
                    'load_scripts_only_with_consent' => true,
                ],
                'group' => 'integrations',
            ],
        ];

        foreach ($settings as $key => $data) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $data['value'],
                    'group' => $data['group'],
                ]
            );
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'installments'],
            [
                'value' => app(InstallmentSettings::class)->all(),
                'group' => 'checkout',
            ],
        );

        ShippingRule::query()->updateOrCreate(
            [
                'type' => 'global',
                'name' => 'Standardna dostava',
            ],
            [
                'category_id' => null,
                'fixed_fee' => 5.00,
                'free_threshold' => 100.00,
                'pickup_enabled' => true,
                'is_active' => true,
                'priority' => 0,
            ]
        );
    }
}
