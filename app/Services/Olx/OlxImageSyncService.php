<?php

namespace App\Services\Olx;

use App\Models\Product;
use App\Models\ProductImage;
use Throwable;

class OlxImageSyncService
{
    public function __construct(
        private readonly OlxApiClient $client,
        private readonly OlxProductImageDownloader $downloader,
        private readonly OlxImageWatermarker $watermarker,
    ) {}

    /**
     * @return array<int, string>
     */
    public function imageUrls(Product $product): array
    {
        return $product->images()
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductImage $image): ?string => $this->resolveSourceUrl($image))
            ->filter()
            ->values()
            ->all();
    }

    public function fingerprint(Product $product): string
    {
        $watermark = $this->watermarker->versionToken();

        return hash('sha256', implode('|', $this->imageUrls($product)).'|'.$watermark);
    }

    public function sync(Product $product, int $listingId, bool $force = false): void
    {
        $urls = $this->imageUrls($product);
        $fingerprint = hash('sha256', implode('|', $urls).'|'.$this->watermarker->versionToken());
        $existingMap = is_array($product->olx_image_map) ? $product->olx_image_map : [];
        $replaceRemoteImages = $force
            || ($existingMap['images'] ?? []) === []
            || ($existingMap['fingerprint'] ?? null) !== $fingerprint;

        if (! $force && ! $replaceRemoteImages && ($existingMap['fingerprint'] ?? null) === $fingerprint) {
            return;
        }

        if ($urls === []) {
            if ($replaceRemoteImages) {
                $this->clearRemoteImages($listingId, $product);
            }

            $product->update(['olx_image_map' => ['fingerprint' => $fingerprint, 'images' => []]]);

            return;
        }

        if ($replaceRemoteImages) {
            $this->clearRemoteImages($listingId, $product);
        }

        $maxImages = max(1, (int) config('bnc.olx_max_images_per_listing', 8));
        $urls = array_slice($urls, 0, $maxImages);

        $uploaded = [];
        $mapImages = [];
        $lastError = null;

        foreach ($urls as $index => $url) {
            $file = $this->downloader->downloadForUpload($url, $index);

            if ($file === null) {
                $lastError = "Preuzimanje slike nije uspjelo: {$url}";

                continue;
            }

            try {
                $batch = $this->client->uploadListingImageFiles($listingId, [$file]);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                continue;
            }

            if ($batch === []) {
                $lastError = 'OLX upload nije vratio nijednu sliku.';

                continue;
            }

            $image = $batch[array_key_last($batch)] ?? null;

            if ($image === null) {
                continue;
            }

            $uploaded[] = $image;
            $mapImages[] = [
                'source_url' => $file['source_url'],
                'olx_image_id' => $image['id'] ?? null,
            ];
        }

        if ($uploaded === []) {
            throw new \RuntimeException($lastError ?? 'OLX nije prihvatio nijednu sliku (preuzimanje ili upload nije uspio).');
        }

        $mainId = $uploaded[0]['id'] ?? null;

        if ($mainId !== null) {
            $this->client->setMainImage($listingId, (int) $mainId);
        }

        $product->update([
            'olx_image_map' => [
                'fingerprint' => $fingerprint,
                'images' => $mapImages,
            ],
            'olx_last_error' => null,
        ]);
    }

    private function clearRemoteImages(int $listingId, Product $product): void
    {
        foreach ($this->knownImageIds($product) as $imageId) {
            $this->deleteImageSafely($listingId, $imageId);
        }

        for ($round = 0; $round < 15; $round++) {
            $remaining = $this->countRemoteImages($listingId);

            if ($remaining === 0) {
                return;
            }

            if ($remaining >= $this->platformImageLimit()) {
                throw new OlxListingImagesLockedException($listingId, $remaining);
            }

            if (! $this->bootstrapDeleteViaUpload($listingId, $product)) {
                break;
            }
        }

        $remaining = $this->countRemoteImages($listingId);

        if ($remaining > 0) {
            if ($remaining >= $this->platformImageLimit()) {
                throw new OlxListingImagesLockedException($listingId, $remaining);
            }

            throw new \RuntimeException(sprintf(
                'Nije moguće obrisati postojeće OLX slike na oglasu %d (preostalo: %d).',
                $listingId,
                $remaining,
            ));
        }
    }

    /**
     * @return array<int, int>
     */
    private function knownImageIds(Product $product): array
    {
        $existingMap = is_array($product->olx_image_map) ? $product->olx_image_map : [];

        return collect($existingMap['images'] ?? [])
            ->pluck('olx_image_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function bootstrapDeleteViaUpload(int $listingId, Product $product): bool
    {
        $urls = $this->imageUrls($product);
        $url = $urls[0] ?? null;

        if ($url === null) {
            return false;
        }

        $file = $this->downloader->downloadForUpload($url, 0);

        if ($file === null) {
            return false;
        }

        try {
            $batch = $this->client->uploadListingImageFiles($listingId, [$file]);
        } catch (Throwable) {
            return false;
        }

        $ids = collect($batch)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($ids as $imageId) {
            $this->deleteImageSafely($listingId, $imageId);
        }

        return $ids !== [];
    }

    private function deleteImageSafely(int $listingId, int $imageId): void
    {
        try {
            $this->client->deleteListingImage($listingId, $imageId);
        } catch (Throwable) {
            // Continue with remaining image ids.
        }
    }

    private function countRemoteImages(int $listingId): int
    {
        $listing = $this->client->getListing($listingId);

        if (! is_array($listing)) {
            return 0;
        }

        $images = $listing['images'] ?? $listing['image'] ?? [];

        if (! is_array($images)) {
            return is_string($images) && $images !== '' ? 1 : 0;
        }

        return count($images);
    }

    private function platformImageLimit(): int
    {
        return max(1, (int) config('bnc.olx_platform_max_images_per_listing', 25));
    }

    private function resolveSourceUrl(ProductImage $image): ?string
    {
        $url = $image->public_url ?: $image->image_url ?: $image->source_url;

        if (! filled($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
