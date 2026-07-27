<?php

namespace App\Support;

class StorefrontConfig
{
    public static function frontendUrl(): ?string
    {
        $url = self::nullableEnv('FRONTEND_URL');

        return $url !== null && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public static function sessionCookieDomain(): ?string
    {
        $configured = self::nullableEnv('SESSION_DOMAIN');

        if ($configured !== null) {
            return self::normalizeCookieDomain($configured);
        }

        $frontendHost = self::frontendHost();

        if ($frontendHost !== null) {
            return self::parentCookieDomain($frontendHost);
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && str_starts_with($appHost, 'api.')) {
            return '.'.substr($appHost, 4);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function corsAllowedOrigins(): array
    {
        $configured = self::parseList((string) env('CORS_ALLOWED_ORIGINS', ''));

        if ($configured !== [] && ! self::containsOnlyLocalhostOrigins($configured)) {
            return self::originVariantsForList($configured);
        }

        $frontend = self::frontendUrl();

        if ($frontend !== null) {
            return self::originVariants($frontend);
        }

        return $configured !== [] ? $configured : ['http://localhost:3000'];
    }

    /**
     * @return array<int, string>
     */
    public static function sanctumStatefulDomains(): array
    {
        $configured = self::sanitizeStatefulDomains(
            self::parseList((string) env('SANCTUM_STATEFUL_DOMAINS', '')),
        );

        if ($configured !== [] && ! self::containsOnlyLocalhostStatefulDomains($configured)) {
            return self::expandWwwVariant(self::preferProductionStatefulDomains($configured));
        }

        $frontendHost = self::frontendHost();

        if ($frontendHost !== null) {
            $hosts = [$frontendHost];

            if (str_starts_with($frontendHost, 'www.')) {
                $hosts[] = substr($frontendHost, 4);
            } else {
                $hosts[] = 'www.'.$frontendHost;
            }

            return array_values(array_unique($hosts));
        }

        return ['localhost', 'localhost:3000', '127.0.0.1', '127.0.0.1:8000', '::1'];
    }

    /**
     * @return array<int, string>
     */
    public static function productionEnvRecommendations(): array
    {
        $lines = [];

        $appUrl = rtrim((string) config('app.url'), '/');
        $frontend = self::frontendUrl();
        $rootDomain = self::deriveStorefrontRootDomain($appUrl, $frontend);

        $recommendedAppUrl = 'https://api.'.$rootDomain;
        $recommendedFrontend = 'https://'.$rootDomain;
        $recommendedSessionDomain = '.'.$rootDomain;
        $recommendedSanctum = $rootDomain.',www.'.$rootDomain;
        $recommendedCors = 'https://'.$rootDomain.',https://www.'.$rootDomain;

        if ($appUrl !== $recommendedAppUrl) {
            $lines[] = 'APP_URL='.$recommendedAppUrl;
        }

        if ($frontend === null) {
            $lines[] = 'FRONTEND_URL='.$recommendedFrontend;
        }

        if (self::nullableEnv('SESSION_DOMAIN') === null || self::nullableEnv('SESSION_DOMAIN') === 'null') {
            $lines[] = 'SESSION_DOMAIN='.$recommendedSessionDomain;
        }

        $lines[] = 'SESSION_SECURE_COOKIE=true';
        $lines[] = 'SANCTUM_STATEFUL_DOMAINS='.$recommendedSanctum;
        $lines[] = 'CORS_ALLOWED_ORIGINS='.$recommendedCors;

        return array_values(array_unique($lines));
    }

    private static function deriveStorefrontRootDomain(string $appUrl, ?string $frontendUrl): string
    {
        if ($frontendUrl !== null) {
            $host = self::frontendHost();

            if (is_string($host) && $host !== '') {
                return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
            }
        }

        $appHost = parse_url($appUrl, PHP_URL_HOST);

        if (is_string($appHost) && str_starts_with($appHost, 'api.')) {
            return substr($appHost, 4);
        }

        return 'bnc.ba';
    }

    /**
     * @param  array<int, string>  $origins
     */
    public static function containsOnlyLocalhostOrigins(array $origins): bool
    {
        if ($origins === []) {
            return true;
        }

        foreach ($origins as $origin) {
            if (! self::isLocalhostOrigin($origin)) {
                return false;
            }
        }

        return true;
    }

    public static function nullableEnv(string $key): ?string
    {
        $value = env($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            return null;
        }

        return $trimmed;
    }

    private static function frontendHost(): ?string
    {
        $frontend = self::frontendUrl();

        if ($frontend === null) {
            return null;
        }

        $host = parse_url($frontend, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    private static function normalizeCookieDomain(string $domain): string
    {
        $host = self::normalizeHost($domain) ?? $domain;

        if ($host === null || $host === '') {
            return $domain;
        }

        if (str_starts_with($host, '.')) {
            return $host;
        }

        return '.'.ltrim($host, '.');
    }

    private static function parentCookieDomain(string $host): string
    {
        $base = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return '.'.ltrim($base, '.');
    }

    /**
     * @return array<int, string>
     */
    private static function parseList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private static function sanitizeStatefulDomains(array $domains): array
    {
        $sanitized = [];

        foreach ($domains as $domain) {
            $host = self::normalizeHost($domain);

            if ($host !== null && $host !== '') {
                $sanitized[] = $host;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private static function normalizeHost(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : null;
        }

        $host = explode('/', $value)[0];

        return $host !== '' ? $host : null;
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private static function preferProductionStatefulDomains(array $domains): array
    {
        $nonLocal = array_values(array_filter(
            $domains,
            fn (string $domain): bool => ! self::isLocalhostStatefulDomain($domain),
        ));

        return $nonLocal !== [] ? $nonLocal : $domains;
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private static function expandWwwVariant(array $domains): array
    {
        $expanded = $domains;

        foreach ($domains as $domain) {
            if (str_starts_with($domain, 'www.')) {
                $expanded[] = substr($domain, 4);
            } elseif (! in_array('www.'.$domain, $domains, true)) {
                $expanded[] = 'www.'.$domain;
            }
        }

        return array_values(array_unique($expanded));
    }

    private static function isLocalhostStatefulDomain(string $domain): bool
    {
        return in_array($domain, ['localhost', '127.0.0.1', '::1'], true)
            || str_starts_with($domain, 'localhost:')
            || str_starts_with($domain, '127.0.0.1:');
    }

    /**
     * @param  array<int, string>  $domains
     */
    private static function containsOnlyLocalhostStatefulDomains(array $domains): bool
    {
        if ($domains === []) {
            return true;
        }

        foreach ($domains as $domain) {
            if (! self::isLocalhostStatefulDomain($domain)) {
                return false;
            }
        }

        return true;
    }

    private static function isLocalhostOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return array<int, string>
     */
    private static function originVariants(string $frontendUrl): array
    {
        $origins = [$frontendUrl];
        $host = parse_url($frontendUrl, PHP_URL_HOST);
        $scheme = parse_url($frontendUrl, PHP_URL_SCHEME) ?: 'https';

        if (! is_string($host) || $host === '') {
            return $origins;
        }

        if (str_starts_with($host, 'www.')) {
            $origins[] = $scheme.'://'.substr($host, 4);
        } else {
            $origins[] = $scheme.'://www.'.$host;
        }

        return array_values(array_unique($origins));
    }

    /**
     * @param  array<int, string>  $origins
     * @return array<int, string>
     */
    private static function originVariantsForList(array $origins): array
    {
        $expanded = [];

        foreach ($origins as $origin) {
            if (str_contains($origin, '://')) {
                $expanded = [...$expanded, ...self::originVariants($origin)];

                continue;
            }

            $expanded[] = $origin;
        }

        return array_values(array_unique($expanded));
    }
}
