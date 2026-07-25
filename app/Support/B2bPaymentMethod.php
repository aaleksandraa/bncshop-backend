<?php

namespace App\Support;

class B2bPaymentMethod
{
    public const INVOICE = 'invoice';

    public static function label(?string $method): string
    {
        return match ($method) {
            self::INVOICE => 'Predračun',
            default => $method ? ucfirst(str_replace('_', ' ', $method)) : 'Predračun',
        };
    }
}
