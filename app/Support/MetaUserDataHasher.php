<?php

namespace App\Support;

class MetaUserDataHasher
{
    public static function hashEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }

    public static function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '387'.substr($digits, 1);
        }

        return hash('sha256', $digits);
    }

    public static function hashName(?string $name): ?string
    {
        $normalized = strtolower(preg_replace('/[^a-z]/', '', strtolower(trim((string) $name))) ?? '');

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }

    public static function hashCity(?string $city): ?string
    {
        $normalized = strtolower(preg_replace('/[^a-z]/', '', strtolower(trim((string) $city))) ?? '');

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }

    public static function hashZip(?string $zip): ?string
    {
        $normalized = strtolower(preg_replace('/[\s-]+/', '', trim((string) $zip)) ?? '');

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }
}
