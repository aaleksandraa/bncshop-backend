<?php

namespace App\Services\Ananas;

use RuntimeException;

class AnanasCatalogWriteGuard
{
    public function __construct(
        private readonly AnanasSyncSettings $settings,
    ) {}

    public function isAllowed(): bool
    {
        return $this->settings->allowCatalogWrites();
    }

    public function assertAllowed(bool $allowProduction = false): void
    {
        if (! $this->settings->allowCatalogWrites()) {
            throw new RuntimeException(
                'Ananas catalog writes are disabled. Set ANANAS_ALLOW_CATALOG_WRITES=true in .env or enable "Dozvoli catalog write API pozive" in Ananas admin settings.',
            );
        }

        if ($this->settings->isProduction() && ! $allowProduction) {
            throw new RuntimeException(
                'Ananas catalog writes on production require explicit --allow-production flag.',
            );
        }
    }
}
