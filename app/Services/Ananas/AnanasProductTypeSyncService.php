<?php

namespace App\Services\Ananas;

use App\Models\AnanasProductType;

class AnanasProductTypeSyncService
{
    public function __construct(
        private readonly AnanasApiClient $client,
    ) {}

    /**
     * @return list<string>
     */
    public function refreshFromApi(): array
    {
        $types = $this->client->getProductTypes();
        $now = now();
        $seen = [];

        foreach ($types as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $normalized = trim($name);
            $seen[] = $normalized;

            AnanasProductType::query()->updateOrCreate(
                ['name' => $normalized],
                ['fetched_at' => $now],
            );
        }

        if ($seen !== []) {
            AnanasProductType::query()
                ->whereNotIn('name', $seen)
                ->delete();
        }

        return $seen;
    }
}
