<?php

namespace App\Services\Ananas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnanasApiClient
{
    private const TOKEN_SCOPE = 'public_api/full_access';

    private bool $authRetriedAfter401 = false;

    public function __construct(
        private readonly AnanasSyncSettings $settings,
        private readonly AnanasRateLimiter $rateLimiter,
    ) {}

    public function tokenCacheKey(): string
    {
        return sprintf('ananas_api_bearer_token_%s', $this->settings->environment());
    }

    public function authenticate(bool $force = false): string
    {
        if (! $force) {
            $cached = Cache::get($this->tokenCacheKey());

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $credentials = $this->settings->credentials();
        $clientId = $credentials['client_id'];
        $clientSecret = $credentials['client_secret'];

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Ananas credentials are not configured. Set ANANAS_CLIENT_ID and ANANAS_CLIENT_SECRET in .env.');
        }

        $tokenUrl = $this->settings->tokenEndpointUrl();

        try {
            $response = $this->authRequest()
                ->post($tokenUrl, [
                    'grantType' => 'CLIENT_CREDENTIALS',
                    'clientId' => $clientId,
                    'clientSecret' => $clientSecret,
                    'scope' => self::TOKEN_SCOPE,
                ]);
        } catch (RequestException $exception) {
            $response = $exception->response;

            if ($response === null) {
                throw $exception;
            }
        }

        if (! $response->successful()) {
            $this->logApiError('POST', $tokenUrl, $response);

            throw new RuntimeException(sprintf(
                'Ananas authentication failed: POST %s returned HTTP %s',
                $tokenUrl,
                $response->status(),
            ));
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw new RuntimeException('Ananas authentication returned an empty access token.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 900);
        $safety = max(60, (int) config('bnc.ananas_token_cache_safety_seconds', 120));
        $ttlSeconds = max(60, $expiresIn - $safety);

        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds($ttlSeconds));

        return $token;
    }

    public function clearTokenCache(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @return list<string>
     */
    public function getProductTypes(): array
    {
        $payload = $this->getJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/product-type',
            [],
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        if (! is_array($payload)) {
            return [];
        }

        return array_values(array_filter($payload, is_string(...)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getWarehouses(): array
    {
        $payload = $this->getJson(
            $this->settings->svcBaseUrl(),
            '/order/api/v1/merchant-integration/merchant-warehouses',
            [],
            AnanasRateLimiter::CATEGORY_WAREHOUSES,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>|array<string, mixed>
     */
    public function getProducts(array $query = []): array
    {
        $payload = $this->getJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/products',
            $query,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>|array<string, mixed>
     */
    public function getBasicProducts(array $query = []): array
    {
        $payload = $this->getJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/basic-products',
            $query,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function getJson(string $baseUrl, string $path, array $query, string $rateCategory): mixed
    {
        return $this->requestJson('GET', $baseUrl, $path, $query, $rateCategory);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function requestJson(
        string $method,
        string $baseUrl,
        string $path,
        array $data = [],
        string $rateCategory = AnanasRateLimiter::CATEGORY_PRODUCTS,
    ): mixed {
        $this->authRetriedAfter401 = false;

        return $this->requestJsonWithRetry($method, $baseUrl, $path, $data, $rateCategory, 0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function requestJsonWithRetry(
        string $method,
        string $baseUrl,
        string $path,
        array $data,
        string $rateCategory,
        int $attempt,
    ): mixed {
        $this->rateLimiter->throttle($rateCategory);

        $response = $this->authorizedRequest($method, $baseUrl, $path, $data);

        if ($response->status() === 401 && ! $this->authRetriedAfter401) {
            $this->authRetriedAfter401 = true;
            $this->clearTokenCache();
            $this->authenticate(true);

            return $this->requestJsonWithRetry($method, $baseUrl, $path, $data, $rateCategory, $attempt);
        }

        if ($response->status() === 401) {
            $this->logApiError($method, $path, $response);

            throw new RuntimeException(sprintf(
                'Ananas API %s %s failed after token refresh: HTTP 401',
                $method,
                $path,
            ));
        }

        if ($response->status() === 429 && $attempt < (int) config('bnc.ananas_api_max_429_retries', 3)) {
            $delayMs = $this->retryDelayMs($response, $attempt);
            usleep($delayMs * 1000);

            return $this->requestJsonWithRetry($method, $baseUrl, $path, $data, $rateCategory, $attempt + 1);
        }

        if (! $response->successful()) {
            $this->logApiError($method, $path, $response);

            throw new RuntimeException(sprintf(
                'Ananas API %s %s failed: HTTP %s',
                $method,
                $path,
                $response->status(),
            ));
        }

        $json = $response->json();

        if ($json === null && $response->body() !== '') {
            throw new RuntimeException(sprintf(
                'Ananas API %s %s returned malformed JSON',
                $method,
                $path,
            ));
        }

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizedRequest(string $method, string $baseUrl, string $path, array $data = []): Response
    {
        $token = $this->authenticate();
        $request = $this->baseRequest($baseUrl)->withToken($token);

        try {
            return match (strtoupper($method)) {
                'GET' => $request->get($path, $data),
                default => throw new RuntimeException("Ananas Phase 1A client supports GET only; attempted {$method}"),
            };
        } catch (RequestException $exception) {
            if ($exception->response !== null) {
                return $exception->response;
            }

            throw $exception;
        }
    }

    private function authRequest(): PendingRequest
    {
        $timeout = (int) config('bnc.ananas_api_timeout', 60);

        return Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withOptions([
                'http_errors' => false,
                'verify' => (bool) config('bnc.ananas_api_verify_ssl', true),
            ]);
    }

    private function baseRequest(string $baseUrl): PendingRequest
    {
        $timeout = (int) config('bnc.ananas_api_timeout', 60);
        $retryCount = (int) config('bnc.ananas_api_retries', 3);

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout($timeout)
            ->withOptions(['http_errors' => false])
            ->retry($retryCount, 2000, function ($exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException) {
                    $status = $exception->response?->status();

                    return in_array($status, [502, 503, 504], true);
                }

                return false;
            })
            ->acceptJson()
            ->withOptions(['verify' => (bool) config('bnc.ananas_api_verify_ssl', true)]);
    }

    private function retryDelayMs(Response $response, int $attempt): int
    {
        $retryAfter = (int) ($response->header('Retry-After') ?? 0);

        if ($retryAfter > 0) {
            return min($retryAfter * 1000, 60_000);
        }

        $base = (int) config('bnc.ananas_429_backoff_base_ms', 1000);
        $jitter = random_int(0, (int) config('bnc.ananas_429_backoff_jitter_ms', 250));

        return min($base * (2 ** $attempt) + $jitter, 60_000);
    }

    private function logApiError(string $method, string $path, Response $response): void
    {
        Log::warning('Ananas API error', [
            'integration' => 'ananas',
            'environment' => $this->settings->environment(),
            'method' => $method,
            'path' => $path,
            'status' => $response->status(),
            'body' => self::redactSensitiveText($response->body()),
        ]);
    }

    public static function redactSensitiveText(string $text): string
    {
        $redacted = preg_replace(
            '/("(?:access_token|clientSecret|clientId|refresh_token|id_token)"\s*:\s*")([^"]*)(")/i',
            '$1[REDACTED]$3',
            $text,
        );

        $redacted = preg_replace(
            '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
            '[REDACTED_JWT]',
            (string) $redacted,
        );

        return (string) $redacted;
    }
}
