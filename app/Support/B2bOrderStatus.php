<?php

namespace App\Support;

class B2bOrderStatus
{
    public const NOVA = 'nova';

    public const U_OBRADI = 'u_obradi';

    public const POTVRDJENA = 'potvrđena';

    public const ISPORUCENA = 'isporučena';

    public const OTKAZANA = 'otkazana';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NOVA => 'Nova',
            self::U_OBRADI => 'U obradi',
            self::POTVRDJENA => 'Potvrđena',
            self::ISPORUCENA => 'Isporučena',
            self::OTKAZANA => 'Otkazana',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::labels());
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }
}
