<?php

namespace App\Services\Sync;

class AttributeNormalizer
{
    /**
     * @return array{normalized_type: string, normalized_value: string|null}
     */
    public function normalize(string $rawValue, ?string $internalType = null): array
    {
        $trimmed = trim($rawValue);

        if ($this->isBooleanValue($trimmed)) {
            return [
                'normalized_type' => 'boolean',
                'normalized_value' => $this->normalizeBoolean($trimmed) ? 'true' : 'false',
            ];
        }

        if ($internalType === 'boolean' && $trimmed !== '') {
            return [
                'normalized_type' => 'boolean',
                'normalized_value' => $this->normalizeBoolean($trimmed) ? 'true' : 'false',
            ];
        }

        if ($this->isNumericValue($trimmed)) {
            $number = $this->normalizeNumber($trimmed);

            return [
                'normalized_type' => 'number',
                'normalized_value' => (string) $number,
            ];
        }

        if ($internalType === 'number' && is_numeric(str_replace(',', '.', $trimmed))) {
            return [
                'normalized_type' => 'number',
                'normalized_value' => (string) $this->normalizeNumber($trimmed),
            ];
        }

        return [
            'normalized_type' => 'text',
            'normalized_value' => $trimmed,
        ];
    }

    private function isBooleanValue(string $value): bool
    {
        return in_array(strtolower($value), ['true', 'false', 'da', 'ne', '1', '0', 'yes', 'no'], true);
    }

    private function normalizeBoolean(string $value): bool
    {
        return in_array(strtolower($value), ['true', 'da', '1', 'yes'], true);
    }

    private function isNumericValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return is_numeric(str_replace(',', '.', $value));
    }

    private function normalizeNumber(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
