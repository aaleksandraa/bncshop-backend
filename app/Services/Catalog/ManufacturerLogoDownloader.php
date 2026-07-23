<?php

namespace App\Services\Catalog;

use App\Models\Manufacturer;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ManufacturerLogoDownloader
{
    /**
     * Download remote logo_url into logo_path for manufacturers that still need it.
     *
     * @return array{downloaded: int, skipped: int, failed: int}
     */
    public function downloadMissing(?int $limit = null, bool $force = false): array
    {
        $query = Manufacturer::query()
            ->whereNotNull('logo_url')
            ->where('logo_url', '!=', '')
            ->orderBy('id');

        if (! $force) {
            $query->where(function ($builder): void {
                $builder
                    ->whereNull('logo_path')
                    ->orWhere('logo_path', '');
            });
        }

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->cursor() as $manufacturer) {
            $result = $this->downloadOne($manufacturer, $force);

            if ($result === true) {
                $downloaded++;
            } elseif ($result === null) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return compact('downloaded', 'skipped', 'failed');
    }

    /**
     * @return bool|null true saved, false failed, null skipped
     */
    public function downloadOne(Manufacturer $manufacturer, bool $force = false): ?bool
    {
        $remoteUrl = trim((string) $manufacturer->logo_url);

        if ($remoteUrl === '' || ! str_starts_with($remoteUrl, 'http')) {
            return null;
        }

        if (
            ! $force
            && filled($manufacturer->logo_path)
            && Storage::disk('public')->exists($manufacturer->logo_path)
        ) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->retry(2, 400)
                ->withOptions([
                    'verify' => (bool) config('bnc.product_image_verify_ssl', true),
                ])
                ->get($remoteUrl);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $contents = $response->body();

        if ($contents === '') {
            return false;
        }

        $extension = $this->resolveExtension($remoteUrl, $response->header('Content-Type'));
        $slug = Str::slug($manufacturer->slug ?: $manufacturer->name) ?: 'brand';
        $path = "manufacturers/logos/{$slug}-{$manufacturer->id}.{$extension}";

        if (
            filled($manufacturer->logo_path)
            && $manufacturer->logo_path !== $path
        ) {
            Storage::disk('public')->delete($manufacturer->logo_path);
        }

        Storage::disk('public')->put($path, $contents);

        $manufacturer->forceFill([
            'logo_path' => $path,
        ])->save();

        return true;
    }

    private function resolveExtension(string $url, ?string $contentType): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $fromUrl = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($fromUrl, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
            return $fromUrl === 'jpeg' ? 'jpg' : $fromUrl;
        }

        $type = strtolower((string) $contentType);

        return match (true) {
            str_contains($type, 'png') => 'png',
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'gif') => 'gif',
            str_contains($type, 'svg') => 'svg',
            default => 'jpg',
        };
    }

    public function previewUrl(Manufacturer $manufacturer): ?string
    {
        return PublicStorageUrl::absoluteFromResolved($manufacturer->logoUrl());
    }
}
