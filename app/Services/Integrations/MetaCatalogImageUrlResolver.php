<?php

namespace App\Services\Integrations;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\PublicStorageUrl;

class MetaCatalogImageUrlResolver
{
    /**
     * @var list<string>
     */
    private const OWN_HOSTS = [
        'bnc.ba',
        'www.bnc.ba',
        'bncshop.ba',
        'www.bncshop.ba',
        'api.bnc.ba',
        'api.bncshop.ba',
        'images.bnc.ba',
        'localhost',
        '127.0.0.1',
    ];

    public function resolve(Product $product): ?string
    {
        $image = $product->defaultImage;

        if ($image instanceof ProductImage && filled($image->local_path)) {
            $fromLocal = $this->fromStorageKey((string) $image->local_path);
            if ($fromLocal !== null) {
                return $fromLocal;
            }
        }

        $candidates = [];

        if ($image instanceof ProductImage) {
            $candidates[] = $image->resolvedUrl();
            $candidates[] = $image->public_url;
            $candidates[] = $image->image_url;
            $candidates[] = $image->source_url;
        }

        $candidates[] = $product->api_default_image_url;

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeCandidate(is_string($candidate) ? $candidate : null);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function catalogOrigin(): string
    {
        $origin = config('bnc.meta_catalog.image_origin')
            ?: config('bnc.media_origin')
            ?: 'https://images.bnc.ba';

        return rtrim(trim((string) $origin), '/');
    }

    private function normalizeCandidate(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/storage/')) {
            return $this->fromStoragePath($url);
        }

        if (str_starts_with($url, 'products/')) {
            return $this->fromStorageKey($url);
        }

        $absolute = PublicStorageUrl::absoluteFromResolved($url);
        if (! is_string($absolute) || $absolute === '') {
            return null;
        }

        if (str_starts_with($absolute, '/storage/')) {
            return $this->fromStoragePath($absolute);
        }

        if (! str_starts_with($absolute, 'http://') && ! str_starts_with($absolute, 'https://')) {
            return null;
        }

        $parts = parse_url($absolute);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($host === '' || $this->isBlockedHost($host)) {
            return null;
        }

        if ($this->isOwnHost($host)) {
            if (str_contains($path, '/storage/')) {
                $storagePath = substr($path, (int) strpos($path, '/storage/'));

                return $this->fromStoragePath($storagePath);
            }

            $trimmedPath = ltrim($path, '/');
            if (str_starts_with($trimmedPath, 'products/')) {
                return $this->catalogOrigin().'/'.$trimmedPath;
            }

            if ($host === 'images.bnc.ba') {
                return $this->stripQuery($absolute);
            }

            return null;
        }

        if (str_starts_with($absolute, 'https://')) {
            return $this->stripQuery($absolute);
        }

        return null;
    }

    private function fromStoragePath(string $storagePath): ?string
    {
        if (! str_starts_with($storagePath, '/storage/')) {
            return null;
        }

        $relative = ltrim(substr($storagePath, strlen('/storage/')), '/');

        return $relative !== '' ? $this->catalogOrigin().'/'.$relative : null;
    }

    private function fromStorageKey(string $key): ?string
    {
        $key = ltrim(str_replace('\\', '/', trim($key)), '/');

        if ($key === '' || str_contains($key, '..')) {
            return null;
        }

        if (str_starts_with($key, 'storage/')) {
            $key = substr($key, strlen('storage/'));
        }

        return $key !== '' ? $this->catalogOrigin().'/'.$key : null;
    }

    private function stripQuery(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.$path;
    }

    private function isOwnHost(string $host): bool
    {
        if (in_array($host, self::OWN_HOSTS, true)) {
            return true;
        }

        return str_ends_with($host, '.bnc.ba') || str_ends_with($host, '.bncshop.ba');
    }

    private function isBlockedHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
