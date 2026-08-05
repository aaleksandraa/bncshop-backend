<?php

namespace App\Services\Olx;

class OlxProcessorValueNormalizer
{
    /**
     * Map full processor strings to OLX select brands (Intel / AMD / Apple).
     */
    public function normalize(string $raw): string
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/\b(apple|macbook|m[1-9]\b|m[1-9]\s+(pro|max|ultra)?)\b/i', $trimmed)) {
            return 'Apple';
        }

        if (preg_match('/\b(intel|core\s*i[3579]|celeron|pentium|xeon|n\d{3,5}|ultra\s*\d)\b/i', $trimmed)) {
            return 'Intel';
        }

        if (preg_match('/\b(amd|ryzen|athlon|threadripper|epyc|fx-\d)\b/i', $trimmed)) {
            return 'AMD';
        }

        return $trimmed;
    }
}
