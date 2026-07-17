<?php

namespace App\Services\Sync;

use Illuminate\Support\Str;

class SupplierNameNormalizer
{
    /**
     * @return array{display_name: string, code: string}
     */
    public function normalize(string $name): array
    {
        $lower = strtolower(trim($name));

        if ($lower === '' || $lower === 'supplier') {
            return [
                'display_name' => 'Nepoznat dobavljač',
                'code' => 'unknown',
            ];
        }

        if (str_contains($lower, 'comtrade') || $lower === 'ct') {
            return [
                'display_name' => 'Comtrade',
                'code' => 'comtrade',
            ];
        }

        if (str_contains($lower, 'arbis')) {
            return [
                'display_name' => 'Arbis',
                'code' => 'arbis',
            ];
        }

        return [
            'display_name' => Str::title($name),
            'code' => Str::slug($name, '_'),
        ];
    }
}
