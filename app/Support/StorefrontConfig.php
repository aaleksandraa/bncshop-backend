<?php

namespace App\Support;

class StorefrontConfig
{
    public static function frontendUrl(): ?string
    {
        $url = trim((string) env('FRONTEND_URL', ''));

        return $url !== '' ? $url : null;
    }

    public static function sessionCookieDomain(): ?string
    {
        $configured = env('SESSION_DOMAIN');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($host) && str_starts_with($host, 'api.')) {
            return '.'.substr($host, 4);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function corsAllowedOrigins(): array
    {
        $configured = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
        )));

        if ($configured !== [] && ! self::containsOnlyLocalhostOrigins($configured)) {
            return $configured;
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
        $configured = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),
        )));

        if ($configured !== [] && ! self::containsOnlyLocalhostStatefulDomains($configured)) {
            return $configured;
        }

        $frontend = self::frontendUrl();

        if ($frontend === null) {
            return $configured;
        }

        $host = parse_url($frontend, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return $configured;
        }

        $hosts = [$host];

        if (str_starts_with($host, 'www.')) {
            $hosts[] = substr($host, 4);
        } else {
            $hosts[] = 'www.'.$host;
        }

        return array_values(array_unique(array_filter($hosts)));
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

    /**
     * @param  array<int, string>  $domains
     */
    private static function containsOnlyLocalhostStatefulDomains(array $domains): bool
    {
        foreach ($domains as $domain) {
            if (! in_array($domain, ['localhost', '127.0.0.1', '::1'], true)
                && ! str_starts_with($domain, 'localhost:')
                && ! str_starts_with($domain, '127.0.0.1:')) {
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
}
