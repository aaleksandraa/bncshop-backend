<?php

namespace App\Support;

final class ResourceSlug
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN = ['null', 'undefined', 'nan', 'true', 'false', 'none'];

    public static function isUsable(?string $slug): bool
    {
        if ($slug === null) {
            return false;
        }

        $trimmed = trim($slug);

        if ($trimmed === '' || strlen($trimmed) > 180) {
            return false;
        }

        if (str_contains($trimmed, '..') || str_contains($trimmed, '\\')) {
            return false;
        }

        return ! in_array(strtolower($trimmed), self::FORBIDDEN, true);
    }

    public static function abortIfInvalid(?string $slug): string
    {
        if (! self::isUsable($slug)) {
            abort(404);
        }

        return trim((string) $slug);
    }
}
