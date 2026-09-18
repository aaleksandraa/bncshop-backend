<?php

namespace App\Services\Ananas;

final class AnanasEanLookup
{
    /**
     * Candidate EAN strings for GET /products?ean= (API requires exact stored form).
     *
     * @return list<string>
     */
    public static function candidateQueryValues(string $ean): array
    {
        $ean = trim($ean);

        if ($ean === '') {
            return [];
        }

        $candidates = [$ean];

        $withoutLeadingZeros = ltrim($ean, '0');

        if ($withoutLeadingZeros !== '' && $withoutLeadingZeros !== $ean) {
            $candidates[] = $withoutLeadingZeros;
        }

        if ($withoutLeadingZeros !== '' && strlen($withoutLeadingZeros) === 12) {
            $candidates[] = '0'.$withoutLeadingZeros;
        }

        if (strlen($ean) === 12) {
            $candidates[] = '0'.$ean;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Whether any string form of $ean is present as a true value in an exists-map.
     *
     * @param  array<string, bool>  $existsMap
     */
    public static function existsInMap(string $ean, array $existsMap): bool
    {
        foreach (self::candidateQueryValues($ean) as $candidate) {
            if (array_key_exists($candidate, $existsMap) && $existsMap[$candidate]) {
                return true;
            }
        }

        return false;
    }
}
