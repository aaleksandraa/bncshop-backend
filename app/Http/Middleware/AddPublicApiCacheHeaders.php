<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddPublicApiCacheHeaders
{
    /**
     * @var list<string>
     */
    private const CACHEABLE_PREFIXES = [
        'api/v1/categories',
        'api/v1/products',
        'api/v1/manufacturers',
        'api/v1/search',
        'api/v1/filters',
        'api/v1/menus',
        'api/v1/pages',
        'api/v1/blog',
        'api/v1/settings/public',
        'api/v1/layout/shell',
        'api/v1/homepage',
        'api/v1/sitemap',
        'api/v1/redirects',
        'api/v1/loyalty/settings',
        'api/v1/installments/settings',
    ];

    /**
     * @var array<string, string>
     */
    private const CACHE_CONTROL_BY_PREFIX = [
        'api/v1/homepage/weekly-offer' => 'private, no-cache, no-store, must-revalidate',
        'api/v1/filters' => 'public, max-age=300, s-maxage=300, stale-while-revalidate=600',
        'api/v1/layout/shell' => 'public, max-age=300, s-maxage=300, stale-while-revalidate=600',
        'api/v1/settings/public' => 'public, max-age=600, s-maxage=600, stale-while-revalidate=900',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        $path = trim($request->path(), '/');

        foreach (self::CACHEABLE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $response->headers->set('Cache-Control', self::resolveCacheControl($path));

                return $response;
            }
        }

        return $response;
    }

    private static function resolveCacheControl(string $path): string
    {
        $matched = null;
        $matchedLength = -1;

        foreach (self::CACHE_CONTROL_BY_PREFIX as $prefix => $cacheControl) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            if (strlen($prefix) > $matchedLength) {
                $matched = $cacheControl;
                $matchedLength = strlen($prefix);
            }
        }

        return $matched ?? 'public, max-age=120, s-maxage=240, stale-while-revalidate=300';
    }
}
