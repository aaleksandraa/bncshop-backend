<?php

namespace App\Services\Olx;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Throwable;

class OlxListingExporter
{
    public function __construct(
        private readonly OlxApiClient $client,
        private readonly OlxExportScope $scope,
        private readonly OlxListingMapper $listingMapper,
        private readonly OlxImageSyncService $imageSyncService,
        private readonly OlxAttributeResolver $attributeResolver,
    ) {}

    /**
     * @return array{action: string, listing_id: int|null}
     */
    public function syncImages(Product $product, bool $allowRecreate = false): array
    {
        if ($this->scope->isLegacyProtected($product)) {
            return ['action' => 'skipped_legacy', 'listing_id' => filled($product->olx_listing_id) ? (int) $product->olx_listing_id : null];
        }

        $mapping = $this->scope->resolveCategoryMapping($product);

        if ($mapping === null) {
            throw new \RuntimeException("Product #{$product->id} has no enabled OLX category mapping.");
        }

        $listingId = (int) $product->olx_listing_id;

        if ($listingId <= 0) {
            throw new \RuntimeException("Product #{$product->id} has no OLX listing id.");
        }

        try {
            $this->imageSyncService->sync($product, $listingId, true);
        } catch (OlxListingImagesLockedException $e) {
            if (! $allowRecreate) {
                throw $e;
            }

            return $this->recreateListing($product, $mapping);
        }

        $product->refresh();
        $this->markSynced(
            $product,
            $listingId,
            $this->listingMapper->map($product, $mapping),
            $product->available_stock > 0 ? 'active' : 'hidden',
        );

        return ['action' => 'images_updated', 'listing_id' => $listingId];
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    private function recreateListing(Product $product, $mapping): array
    {
        $oldListingId = filled($product->olx_listing_id) ? (int) $product->olx_listing_id : null;
        $payload = $this->listingMapper->map($product, $mapping);
        $response = $this->client->createListing($payload);
        $listingId = (int) ($response['id'] ?? 0);

        if ($listingId <= 0) {
            throw new \RuntimeException('OLX create listing returned no id during recreate.');
        }

        try {
            $this->imageSyncService->sync($product, $listingId, true);
            $this->client->publishListing($listingId);
        } catch (Throwable $e) {
            try {
                $this->client->deleteListing($listingId);
            } catch (Throwable) {
                // Best effort cleanup for unpublished draft listing.
            }

            throw $e;
        }

        if ($oldListingId !== null && $oldListingId !== $listingId) {
            try {
                $this->client->deleteListing($oldListingId);
            } catch (Throwable $e) {
                Log::warning('OLX old listing delete failed after recreate', [
                    'product_id' => $product->id,
                    'listing_id' => $oldListingId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->markSynced($product, $listingId, $payload, $product->available_stock > 0 ? 'active' : 'hidden');

        return ['action' => 'create', 'listing_id' => $listingId];
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    public function export(Product $product, string $action): array
    {
        if ($this->scope->isLegacyProtected($product)) {
            return ['action' => 'skipped_legacy', 'listing_id' => filled($product->olx_listing_id) ? (int) $product->olx_listing_id : null];
        }

        $mapping = $this->scope->resolveCategoryMapping($product);

        if ($mapping === null) {
            throw new \RuntimeException("Product #{$product->id} has no enabled OLX category mapping.");
        }

        try {
            return match ($action) {
                'create' => $this->create($product, $mapping),
                'update' => $this->update($product, $mapping),
                'hide' => $this->hide($product),
                'unhide' => $this->unhide($product, $mapping),
                default => throw new \InvalidArgumentException("Unknown export action: {$action}"),
            };
        } catch (Throwable $e) {
            $product->update([
                'olx_listing_status' => 'error',
                'olx_last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    private function create(Product $product, $mapping): array
    {
        $olxCategoryId = (int) $mapping->olx_category_id;
        $missing = $this->attributeResolver->missingRequiredForPublish($product, $olxCategoryId);

        if ($missing !== []) {
            throw new \RuntimeException(
                'Nedostaju obavezni OLX atributi: '.implode(', ', array_map(
                    fn (int $id, string $label): string => "{$label} (#{$id})",
                    array_keys($missing),
                    array_values($missing),
                )),
            );
        }

        $payload = $this->listingMapper->map($product, $mapping);
        $response = $this->client->createListing($payload);
        $listingId = (int) ($response['id'] ?? 0);

        if ($listingId <= 0) {
            throw new \RuntimeException('OLX create listing returned no id.');
        }

        $imageWarning = $this->syncImagesStrict($product, $listingId, true);
        $this->client->publishListing($listingId);
        $this->markSynced($product, $listingId, $payload, 'active', $imageWarning);

        return ['action' => 'create', 'listing_id' => $listingId];
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    private function update(Product $product, $mapping): array
    {
        $listingId = (int) $product->olx_listing_id;
        $payload = $this->listingMapper->map($product, $mapping);
        $this->client->updateListing($listingId, $payload);
        $this->syncImagesStrict($product, $listingId, false);
        $this->markSynced($product, $listingId, $payload, $product->available_stock > 0 ? 'active' : 'hidden');

        return ['action' => 'update', 'listing_id' => $listingId];
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    private function hide(Product $product): array
    {
        $listingId = (int) $product->olx_listing_id;
        $this->client->hideListing($listingId);
        $product->update([
            'olx_listing_status' => 'hidden',
            'olx_synced_at' => now(),
            'olx_last_error' => null,
        ]);

        return ['action' => 'hide', 'listing_id' => $listingId];
    }

    /**
     * @return array{action: string, listing_id: int|null}
     */
    private function unhide(Product $product, $mapping): array
    {
        $listingId = (int) $product->olx_listing_id;
        $this->client->unhideListing($listingId);
        $payload = $this->listingMapper->map($product, $mapping);
        $this->client->updateListing($listingId, $payload);
        $this->markSynced($product, $listingId, $payload, 'active');

        return ['action' => 'unhide', 'listing_id' => $listingId];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markSynced(Product $product, int $listingId, array $payload, string $status, ?string $imageWarning = null): void
    {
        $product->update([
            'olx_listing_id' => (string) $listingId,
            'olx_listing_status' => $status,
            'olx_export_hash' => $this->listingMapper->fingerprintPayload($payload),
            'olx_managed' => true,
            'olx_synced_at' => now(),
            'olx_last_error' => $imageWarning,
        ]);
    }

    private function syncImagesStrict(Product $product, int $listingId, bool $force, bool $allowRecreate = false): ?string
    {
        try {
            $this->imageSyncService->sync($product, $listingId, $force || $product->olx_image_map === null);

            return null;
        } catch (OlxListingImagesLockedException $e) {
            if (! $allowRecreate) {
                throw $e;
            }

            $mapping = $this->scope->resolveCategoryMapping($product);

            if ($mapping === null) {
                throw $e;
            }

            $this->recreateListing($product, $mapping);

            return null;
        } catch (Throwable $e) {
            Log::warning('OLX image sync skipped', [
                'product_id' => $product->id,
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return 'Slike nisu učitane: '.$e->getMessage();
        }
    }
}
