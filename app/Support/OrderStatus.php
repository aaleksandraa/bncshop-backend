<?php

namespace App\Support;

class OrderStatus
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
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

    public static function label(string $status): string
    {
        return self::labels()[self::normalize($status)] ?? $status;
    }

    public static function normalize(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'nova';
        }

        return $status;
    }

    /**
     * @return array<int, string>
     */
    public static function filterOptions(): array
    {
        return array_keys(self::labels());
    }
}
