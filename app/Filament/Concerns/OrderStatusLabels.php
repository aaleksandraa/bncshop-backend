<?php

namespace App\Filament\Concerns;

trait OrderStatusLabels
{
    /**
     * @return array<string, string>
     */
    protected static function orderStatusOptions(): array
    {
        return [
            'nova' => 'Nova',
            'u_obradi' => 'U obradi',
            'potvrđena' => 'Potvrđena',
            'spakovano' => 'Spakovano',
            'spremno_za_preuzimanje' => 'Spremno za preuzimanje',
            'poslano' => 'Poslano',
            'isporučeno' => 'Isporučeno',
            'otkazano' => 'Otkazano',
            'vraćeno' => 'Vraćeno',
            'neuspjela_dostava' => 'Neuspjela dostava',
            'arhivirano' => 'Arhivirano',
        ];
    }

    protected static function orderStatusColor(string $status): string
    {
        return match ($status) {
            'nova' => 'gray',
            'u_obradi' => 'gray',
            'potvrđena' => 'warning',
            'spakovano' => 'warning',
            'spremno_za_preuzimanje' => 'success',
            'poslano' => 'warning',
            'isporučeno' => 'success',
            'otkazano' => 'danger',
            'vraćeno' => 'danger',
            'neuspjela_dostava' => 'danger',
            'arhivirano' => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function paymentMethodOptions(): array
    {
        return [
            'pay_on_delivery' => 'Plaćanje pouzećem',
            'cod' => 'Plaćanje pouzećem',
            'bank_transfer' => 'Virman',
            'card' => 'Kartica',
        ];
    }

    protected static function paymentMethodLabel(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return static::paymentMethodOptions()[$method] ?? $method;
    }

    /**
     * @return array<string, string>
     */
    protected static function shippingMethodOptions(): array
    {
        return [
            'delivery' => 'Dostava na adresu',
            'pickup' => 'Preuzimanje u poslovnici',
        ];
    }

    protected static function shippingMethodLabel(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return static::shippingMethodOptions()[$method] ?? $method;
    }

    protected static function formatMoney(float|string|null $amount): string
    {
        $symbol = (string) config('bnc.currency_symbol', 'KM');

        return number_format((float) $amount, 2, ',', '.').' '.$symbol;
    }
}
