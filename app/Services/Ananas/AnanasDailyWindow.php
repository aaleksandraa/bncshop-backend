<?php

namespace App\Services\Ananas;

use Carbon\Carbon;

class AnanasDailyWindow
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Europe/Sarajevo');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function inPriceStockWindow(?Carbon $at = null): bool
    {
        $at ??= self::now();
        $minutes = $at->hour * 60 + $at->minute;

        return $minutes >= 0 && $minutes < 120;
    }

    public static function inDiscountWindow(?Carbon $at = null): bool
    {
        $at ??= self::now();
        $minutes = $at->hour * 60 + $at->minute;

        return $minutes >= 0 && $minutes < 180;
    }

    public static function todayDateString(): string
    {
        return self::now()->toDateString();
    }
}
