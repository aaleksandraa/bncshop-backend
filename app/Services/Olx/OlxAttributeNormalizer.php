<?php

namespace App\Services\Olx;

use App\Models\OlxCategoryAttribute;

class OlxAttributeNormalizer
{
    /**
     * Snap free-text values to valid OLX select options when possible.
     */
    public function snapToSelectOption(string $value, OlxCategoryAttribute $meta): string
    {
        $options = $this->optionValues($meta);

        if ($options === []) {
            return $value;
        }

        $trimmed = trim($value);

        foreach ($options as $option) {
            if (strcasecmp($trimmed, $option) === 0) {
                return $option;
            }
        }

        $normalized = $this->normalizeKey($trimmed);

        foreach ($options as $option) {
            if ($this->normalizeKey($option) === $normalized) {
                return $option;
            }
        }

        if ($this->looksNumeric($trimmed)) {
            $numeric = $this->toFloat($trimmed);

            if ($numeric !== null) {
                $numericMatch = $this->closestNumericOption($numeric, $options);

                if ($numericMatch !== null) {
                    return $numericMatch;
                }
            }
        }

        foreach ($options as $option) {
            if (str_contains($this->normalizeKey($option), $normalized)
                || str_contains($normalized, $this->normalizeKey($option))) {
                return $option;
            }
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function optionValues(OlxCategoryAttribute $meta): array
    {
        $raw = $meta->options_json ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(function ($option): ?string {
                if (is_string($option)) {
                    return trim($option);
                }

                if (is_array($option)) {
                    foreach (['value', 'label', 'name', 'title'] as $key) {
                        if (! empty($option[$key])) {
                            return trim((string) $option[$key]);
                        }
                    }
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');
    }

    private function looksNumeric(string $value): bool
    {
        return (bool) preg_match('/^\d+(?:[.,]\d+)?/', $value);
    }

    private function toFloat(string $value): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $value, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $options
     */
    private function closestNumericOption(float $value, array $options): ?string
    {
        $best = null;
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($options as $option) {
            if (! preg_match('/(\d+(?:[.,]\d+)?)/', $option, $m)) {
                continue;
            }

            $optionValue = (float) str_replace(',', '.', $m[1]);
            $diff = abs($optionValue - $value);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $option;
            }
        }

        return $best;
    }
}
