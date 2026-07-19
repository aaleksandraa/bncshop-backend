<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class B2bSetting extends Model
{
    private const CACHE_KEY = 'b2b_settings';

    protected $fillable = [
        'default_customer_discount_percent',
        'admin_notification_email',
        'notify_customers_on_new_product',
    ];

    protected function casts(): array
    {
        return [
            'default_customer_discount_percent' => 'decimal:2',
            'notify_customers_on_new_product' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function defaultAdminNotificationEmail(): string
    {
        foreach ([
            config('b2b.mail.admin_notification_email'),
            config('bnc.admin_notification_email'),
            env('SELLER_EMAIL'),
            config('mail.from.address'),
        ] as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return 'b2b@bncshop.ba';
    }

    public static function instance(): self
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => static::query()->firstOrCreate([], [
            'default_customer_discount_percent' => 0,
            'admin_notification_email' => static::defaultAdminNotificationEmail(),
            'notify_customers_on_new_product' => false,
        ]));
    }

    public static function adminNotificationEmail(): string
    {
        $settings = static::instance();

        return filled($settings->admin_notification_email)
            ? (string) $settings->admin_notification_email
            : static::defaultAdminNotificationEmail();
    }
}
