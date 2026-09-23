<?php

namespace App\Services\Olx;

use App\Models\Product;

class OlxChangeDetector
{
    public function __construct(
        private readonly OlxExportScope $scope,
        private readonly OlxListingMapper $listingMapper,
        private readonly OlxAttributeResolver $attributeResolver,
    ) {}

    /**
     * Scan eligible products and return product IDs grouped by action.
     * Stores IDs only (not full models) to keep memory bounded on large catalogs.
     *
     * @return array{
     *     create: list<int>,
     *     update: list<int>,
     *     hide: list<int>,
     *     unhide: list<int>,
     *     delete: list<int>,
     *     unchanged: int,
     *     scanned: int,
     *     frozen_invalid_create: int
     * }
     */
    public function detect(bool $forceAll = false, ?callable $onProgress = null): array
    {
        /** @var list<int> $create */
        $create = [];
        /** @var list<int> $update */
        $update = [];
        /** @var list<int> $hide */
        $hide = [];
        /** @var list<int> $unhide */
        $unhide = [];
        /** @var list<int> $delete */
        $delete = [];
        $unchanged = 0;
        $scanned = 0;
        $frozenInvalidCreate = 0;

        $eligibleIds = $this->scope->scopedCategoryIds();

        if ($eligibleIds === []) {
            $this->collectDelisted($delete, $hide, $scanned);

            return $this->result($create, $update, $hide, $unhide, $delete, $unchanged, $scanned, $frozenInvalidCreate);
        }

        Product::query()
            ->with(['category.parent', 'images', 'attributeValues.attributeDefinition', 'manufacturer'])
            ->whereIn('category_id', $eligibleIds)
            ->where('is_public', true)
            ->where('status', 'active')
            ->chunkById(50, function ($products) use (&$create, &$update, &$hide, &$unhide, &$unchanged, &$scanned, &$frozenInvalidCreate, $forceAll, $onProgress): void {
                foreach ($products as $product) {
                    $scanned++;

                    if (! $this->scope->isEligible($product)) {
                        continue;
                    }

                    $mapping = $this->scope->resolveCategoryMapping($product);

                    if ($mapping === null) {
                        continue;
                    }

                    $payload = $this->listingMapper->map($product, $mapping);
                    $hash = $this->listingMapper->fingerprintPayload($payload);
                    $hasListing = filled($product->olx_listing_id);

                    if (! $hasListing) {
                        if ($product->available_stock > 0) {
                            $missing = $this->attributeResolver->missingRequiredForPublish(
                                $product,
                                (int) $mapping->olx_category_id,
                            );

                            if ($missing !== []) {
                                $this->persistInvalidCreate($product, $hash, $missing);
                                $frozenInvalidCreate++;
                            } elseif ($this->isFrozenInvalidCreate($product, $hash, $forceAll)) {
                                $frozenInvalidCreate++;
                            } else {
                                $create[] = (int) $product->id;
                            }
                        }

                        continue;
                    }

                    if ($product->available_stock <= 0 && $product->olx_listing_status !== 'hidden') {
                        $hide[] = (int) $product->id;

                        continue;
                    }

                    if ($product->available_stock > 0 && $product->olx_listing_status === 'hidden') {
                        $unhide[] = (int) $product->id;
                    }

                    if ($forceAll || $product->olx_export_hash !== $hash) {
                        $update[] = (int) $product->id;

                        continue;
                    }

                    $unchanged++;
                }

                if ($onProgress !== null) {
                    $onProgress($scanned);
                }
            });

        $this->collectDelisted($delete, $hide, $scanned);

        return $this->result($create, $update, $hide, $unhide, $delete, $unchanged, $scanned, $frozenInvalidCreate);
    }

    /**
     * Cheap stock/visibility pass: hide, unhide, delete. No new listings.
     *
     * @return array{
     *     create: list<int>,
     *     update: list<int>,
     *     hide: list<int>,
     *     unhide: list<int>,
     *     delete: list<int>,
     *     unchanged: int,
     *     scanned: int,
     *     frozen_invalid_create: int
     * }
     */
    public function detectStock(?callable $onProgress = null): array
    {
        $create = [];
        $update = [];
        $hide = [];
        $unhide = [];
        $delete = [];
        $unchanged = 0;
        $scanned = 0;

        Product::query()
            ->whereNotNull('olx_listing_id')
            ->where('olx_managed', true)
            ->chunkById(100, function ($products) use (&$hide, &$unhide, &$delete, &$unchanged, &$scanned, $onProgress): void {
                foreach ($products as $product) {
                    $scanned++;

                    if ($this->scope->isLegacyProtected($product)) {
                        $unchanged++;

                        continue;
                    }

                    if ($this->shouldDeleteListing($product)) {
                        $delete[] = (int) $product->id;

                        continue;
                    }

                    if ($product->available_stock <= 0 && $product->olx_listing_status !== 'hidden') {
                        $hide[] = (int) $product->id;

                        continue;
                    }

                    if ($product->available_stock > 0 && $product->olx_listing_status === 'hidden') {
                        $unhide[] = (int) $product->id;

                        continue;
                    }

                    $unchanged++;
                }

                if ($onProgress !== null) {
                    $onProgress($scanned);
                }
            });

        return $this->result($create, $update, $hide, $unhide, $delete, $unchanged, $scanned, 0);
    }

    /**
     * @param  list<int>  $delete
     * @param  list<int>  $hide
     */
    private function collectDelisted(array &$delete, array &$hide, int &$scanned): void
    {
        $alreadyQueued = array_fill_keys([...$delete, ...$hide], true);

        Product::query()
            ->whereNotNull('olx_listing_id')
            ->where('olx_managed', true)
            ->chunkById(100, function ($products) use (&$delete, &$hide, &$scanned, $alreadyQueued): void {
                foreach ($products as $product) {
                    $id = (int) $product->id;

                    if (isset($alreadyQueued[$id]) || $this->scope->isLegacyProtected($product)) {
                        continue;
                    }

                    if ($this->shouldDeleteListing($product)) {
                        $scanned++;
                        $delete[] = $id;

                        continue;
                    }

                    if ($product->available_stock <= 0 && $product->olx_listing_status !== 'hidden') {
                        $scanned++;
                        $hide[] = $id;
                    }
                }
            });
    }

    private function shouldDeleteListing(Product $product): bool
    {
        return ! $this->scope->isEligible($product)
            || $this->scope->resolveCategoryMapping($product) === null;
    }

    /**
     * @param  array<int, string>  $missing
     */
    private function persistInvalidCreate(Product $product, string $hash, array $missing): void
    {
        $message = 'Nedostaju obavezni OLX atributi: '.implode(', ', array_map(
            fn (int $id, string $label): string => "{$label} (#{$id})",
            array_keys($missing),
            array_values($missing),
        ));

        if (
            $product->olx_listing_status === 'error'
            && $product->olx_last_error === $message
            && $product->olx_export_hash === $hash
        ) {
            return;
        }

        $product->update([
            'olx_listing_status' => 'error',
            'olx_last_error' => $message,
            'olx_export_hash' => $hash,
        ]);
    }

    private function isFrozenInvalidCreate(Product $product, string $hash, bool $forceAll): bool
    {
        if ($forceAll || $product->olx_listing_status !== 'error' || $product->olx_export_hash !== $hash) {
            return false;
        }

        $error = (string) $product->olx_last_error;

        return str_contains($error, 'obavezni OLX atributi')
            || str_contains($error, 'Nedostaju obavezni')
            || str_contains($error, 'validation_failed');
    }

    /**
     * @param  list<int>  $create
     * @param  list<int>  $update
     * @param  list<int>  $hide
     * @param  list<int>  $unhide
     * @param  list<int>  $delete
     * @return array{
     *     create: list<int>,
     *     update: list<int>,
     *     hide: list<int>,
     *     unhide: list<int>,
     *     delete: list<int>,
     *     unchanged: int,
     *     scanned: int,
     *     frozen_invalid_create: int
     * }
     */
    private function result(
        array $create,
        array $update,
        array $hide,
        array $unhide,
        array $delete,
        int $unchanged,
        int $scanned,
        int $frozenInvalidCreate,
    ): array {
        return [
            'create' => $this->newestFirst($create),
            'update' => $update,
            'hide' => $hide,
            'unhide' => $unhide,
            'delete' => $delete,
            'unchanged' => $unchanged,
            'scanned' => $scanned,
            'frozen_invalid_create' => $frozenInvalidCreate,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function newestFirst(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        rsort($ids);

        return $ids;
    }
}
