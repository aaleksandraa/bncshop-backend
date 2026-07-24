<?php

namespace App\Services\Catalog;

use App\Models\Manufacturer;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ManufacturerLogoDownloader
{
    /**
     * Resolve missing logo URLs from A1 storefront, then download into logo_path.
     *
     * @return array{resolved: int, downloaded: int, skipped: int, failed: int, unmatched: int}
     */
    public function downloadMissing(?int $limit = null, bool $force = false): array
    {
        $resolved = $this->resolveMissingLogoUrlsFromCatalog();

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

        $unmatched = Manufacturer::query()
            ->where(function ($builder): void {
                $builder->whereNull('logo_url')->orWhere('logo_url', '');
            })
            ->where(function ($builder): void {
                $builder->whereNull('logo_path')->orWhere('logo_path', '');
            })
            ->count();

        return compact('resolved', 'downloaded', 'skipped', 'failed', 'unmatched');
    }

    /**
     * Pull logo URLs from a1team.ba/brendovi (and per-brand pages) into logo_url.
     */
    public function resolveMissingLogoUrlsFromCatalog(): int
    {
        $catalog = $this->fetchA1BrandLogoCatalog();

        if ($catalog === []) {
            Log::warning('Manufacturer logo catalog from A1 storefront is empty.');

            return 0;
        }

        $resolved = 0;
        $brandPageLookups = 0;
        $maxBrandPageLookups = 80;

        $manufacturers = Manufacturer::query()
            ->where(function ($builder): void {
                $builder
                    ->whereNull('logo_url')
                    ->orWhere('logo_url', '');
            })
            ->withCount([
                'products as products_count' => fn ($builder) => $builder->public()->active(),
            ])
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo_url', 'logo_path']);

        foreach ($manufacturers as $manufacturer) {
            $logoUrl = $this->matchCatalogLogo($catalog, $manufacturer);

            if ($logoUrl === null && $brandPageLookups < $maxBrandPageLookups) {
                $brandPageLookups++;
                $logoUrl = $this->fetchLogoFromBrandPage($manufacturer);
            }

            if ($logoUrl === null) {
                continue;
            }

            $manufacturer->forceFill([
                'logo_url' => $logoUrl,
            ])->save();

            $resolved++;
        }

        return $resolved;
    }

    /**
     * @return array<string, string> normalizedKey => absolute logo URL
     */
    public function fetchA1BrandLogoCatalog(): array
    {
        $base = rtrim((string) config('bnc.a1_api_base_url', 'https://a1team.ba'), '/');

        try {
            $response = Http::timeout(45)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'BNCShopLogoSync/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions([
                    'verify' => (bool) config('bnc.a1_api_verify_ssl', true),
                ])
                ->get("{$base}/brendovi");
        } catch (Throwable $e) {
            Log::warning('Failed fetching A1 brand directory.', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parseBrandLogoCatalog($response->body(), $base);
    }

    /**
     * @return array<string, string>
     */
    public function parseBrandLogoCatalog(string $html, string $baseUrl): array
    {
        $catalog = [];

        if (preg_match_all(
            '#brendovi/([a-z0-9-]+)[\s\S]{0,500}?(/storage/images/[a-z0-9-]+\.(?:webp|png|jpe?g|svg))#i',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $slug = strtolower($match[1]);

                if (str_starts_with($slug, 'page-')) {
                    continue;
                }

                $path = $match[2];
                $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
                $key = $this->normalizeBrandKey($slug);

                if ($key !== '') {
                    $catalog[$key] = $url;
                }
            }
        }

        if (preg_match_all(
            '#(/storage/images/[a-z0-9-]+\.(?:webp|png|jpe?g|svg))[\s\S]{0,500}?brendovi/([a-z0-9-]+)#i',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $slug = strtolower($match[2]);

                if (str_starts_with($slug, 'page-')) {
                    continue;
                }

                $path = $match[1];
                $url = str_starts_with($path, 'http') ? $path : $baseUrl.$path;
                $key = $this->normalizeBrandKey($slug);

                if ($key !== '' && ! isset($catalog[$key])) {
                    $catalog[$key] = $url;
                }
            }
        }

        return $catalog;
    }

    /**
     * @param  array<string, string>  $catalog
     */
    private function matchCatalogLogo(array $catalog, Manufacturer $manufacturer): ?string
    {
        $candidates = [
            $this->normalizeBrandKey((string) $manufacturer->slug),
            $this->normalizeBrandKey((string) $manufacturer->name),
        ];

        foreach ($candidates as $key) {
            if ($key !== '' && isset($catalog[$key])) {
                return $catalog[$key];
            }
        }

        return null;
    }

    private function fetchLogoFromBrandPage(Manufacturer $manufacturer): ?string
    {
        $base = rtrim((string) config('bnc.a1_api_base_url', 'https://a1team.ba'), '/');
        $slugs = array_values(array_unique(array_filter([
            Str::slug((string) $manufacturer->slug),
            Str::slug((string) $manufacturer->name),
        ])));

        foreach ($slugs as $slug) {
            try {
                $response = Http::timeout(20)
                    ->retry(1, 300)
                    ->withHeaders([
                        'User-Agent' => 'BNCShopLogoSync/1.0',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->withOptions([
                        'verify' => (bool) config('bnc.a1_api_verify_ssl', true),
                    ])
                    ->get("{$base}/brendovi/{$slug}");
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $html = $response->body();

            if (preg_match('#(/storage/images/[a-z0-9-]+\.(?:webp|png|jpe?g|svg))#i', $html, $match)) {
                $path = $match[1];

                return str_starts_with($path, 'http') ? $path : $base.$path;
            }
        }

        return null;
    }

    public function normalizeBrandKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? '';
        // a1team sometimes uses trailing counters: tp-link-2
        $key = preg_replace('/\d+$/', '', $key) ?? $key;

        return $key;
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
                ->withHeaders([
                    'User-Agent' => 'BNCShopLogoSync/1.0',
                    'Accept' => 'image/*,*/*',
                ])
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
            'logo_url' => $remoteUrl,
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
