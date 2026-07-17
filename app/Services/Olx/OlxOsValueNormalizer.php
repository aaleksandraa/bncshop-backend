<?php

namespace App\Services\Olx;

class OlxOsValueNormalizer
{
    /**
     * Map imported BNC OS attribute values to OLX select options.
     */
    public function normalize(string $raw): string
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return $trimmed;
        }

        $lower = mb_strtolower($trimmed);

        if (str_contains($lower, 'win 11') || str_contains($lower, 'windows 11')) {
            return 'Win 11';
        }

        if (str_contains($lower, 'win 10') || str_contains($lower, 'windows 10')) {
            return 'Win 10';
        }

        if (str_contains($lower, 'freedos') || str_contains($lower, 'bez os') || preg_match('/\bdos\b/u', $lower)) {
            return 'Nema';
        }

        if (str_contains($lower, 'mac os') || str_contains($lower, 'macos')) {
            return 'Mac OS';
        }

        if (str_contains($lower, 'linux')) {
            return 'Linux';
        }

        return $trimmed;
    }
}
