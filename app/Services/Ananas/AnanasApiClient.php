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
        private readonly AnanasCatalogWriteGuard $writeGuard,
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
     * POST bulk import. Body is a JSON array of product payloads.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{progress_id: string|null, raw: array<string, mixed>}
     */
    public function importProducts(array $items, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        if ($items === []) {
            throw new RuntimeException('Ananas import requires at least one product payload.');
        }

        $payload = $this->postJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/import',
            $items,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        $progressId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';

        return [
            'progress_id' => $progressId !== '' ? $progressId : null,
            'raw' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @param  list<string>  $eans
     * @return array<string, bool>
     */
    public function checkEansExist(array $eans): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $ean): ?string => is_string($ean) && trim($ean) !== '' ? trim($ean) : null,
            $eans,
        )));

        if ($normalized === []) {
            return [];
        }

        $payload = $this->postJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/ean/exists',
            $normalized,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        $existsMap = $this->normalizeEanExistsPayload($payload);
        $result = [];

        foreach ($normalized as $ean) {
            $result[$ean] = AnanasEanLookup::existsInMap($ean, $existsMap);
        }

        return $result;
    }

    public function eanExistsInMasterCatalog(string $ean): bool
    {
        $ean = trim($ean);

        if ($ean === '') {
            return false;
        }

        $result = $this->checkEansExist([$ean]);

        return (bool) ($result[$ean] ?? false);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function updateProductsBulk(array $items, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        if ($items === []) {
            return [];
        }

        $payload = $this->putJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/product/bulk',
            $items,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? array_values(array_filter($payload, is_array(...))) : [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateSingleProduct(int $ananasProductId, array $body, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        $payload = $this->putJson(
            $this->settings->productBaseUrl(),
            '/product/api/v1/merchant-integration/product/'.$ananasProductId,
            $body,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  list<int>  $merchantInventoryIds
     * @return array{progress_id: string|null, raw: array<string, mixed>}
     */
    public function publishProducts(array $merchantInventoryIds, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        $ids = array_values(array_filter(array_map('intval', $merchantInventoryIds)));

        if ($ids === []) {
            throw new RuntimeException('Ananas publish requires at least one merchant inventory id.');
        }

        return $this->submitProgressJob(
            '/product/api/v1/merchant-integration/product/publish',
            $ids,
        );
    }

    /**
     * @param  list<int>  $merchantInventoryIds
     * @return array{progress_id: string|null, raw: array<string, mixed>}
     */
    public function unpublishProducts(array $merchantInventoryIds, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        $ids = array_values(array_filter(array_map('intval', $merchantInventoryIds)));

        if ($ids === []) {
            throw new RuntimeException('Ananas unpublish requires at least one merchant inventory id.');
        }

        return $this->submitProgressJob(
            '/product/api/v1/merchant-integration/product/unpublish',
            $ids,
        );
    }

    /**
     * POST /payment/.../discounts — schedule bulk actions.
     * Docs currency enum is RSD only. Body: { discounts: [...] }.
     *
     * @param  list<array<string, mixed>>  $discounts
     * @return array<string, mixed>
     */
    public function scheduleDiscounts(array $discounts, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        if ($discounts === []) {
            throw new RuntimeException('Ananas discount schedule requires at least one discount payload.');
        }

        $payload = $this->postJson(
            $this->settings->productBaseUrl(),
            '/payment/api/v1/merchant-integration/discounts',
            ['discounts' => array_values($discounts)],
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * GET /payment/.../discounts?dateFrom=&dateTo= (dd/MM/yyyy).
     *
     * @return list<array<string, mixed>>
     */
    public function getDiscounts(string $dateFrom, string $dateTo): array
    {
        $payload = $this->getJson(
            $this->settings->productBaseUrl(),
            '/payment/api/v1/merchant-integration/discounts',
            [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ],
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['discounts', 'content', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], is_array(...)));
            }
        }

        return [];
    }

    /**
     * PUT /payment/.../discounts/{discountId}/cancellations
     *
     * @return array<string, mixed>
     */
    public function cancelDiscount(string $discountId, bool $allowProduction = false): array
    {
        $this->writeGuard->assertAllowed($allowProduction);

        $id = trim($discountId);

        if ($id === '') {
            throw new RuntimeException('Ananas discount cancel requires a discountId UUID.');
        }

        $payload = $this->putJson(
            $this->settings->productBaseUrl(),
            '/payment/api/v1/merchant-integration/discounts/'.$id.'/cancellations',
            [],
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{progress_id: string|null, raw: array<string, mixed>}
     */
    private function submitProgressJob(string $path, array $body): array
    {
        $payload = $this->postJson(
            $this->settings->productBaseUrl(),
            $path,
            $body,
            AnanasRateLimiter::CATEGORY_PRODUCTS,
        );

        $progressId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';

        return [
            'progress_id' => $progressId !== '' ? $progressId : null,
            'raw' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public function findProductByEan(string $ean): ?array
    {
        $found = $this->findProductByEanDetailed($ean);

        return $found['product'] ?? null;
    }

    /**
     * @return array{product: array<string, mixed>|null, matched_ean: string|null, via: string|null}
     */
    public function findProductByEanDetailed(string $ean): array
    {
        $empty = [
            'product' => null,
            'matched_ean' => null,
            'via' => null,
        ];

        foreach (AnanasEanLookup::candidateQueryValues($ean) as $candidate) {
            $payload = $this->getProducts(['ean' => $candidate, 'page' => 0, 'size' => 1]);
            $items = $this->normalizeListPayload($payload);
            $product = $items[0] ?? null;

            if (is_array($product)) {
                return [
                    'product' => $product,
                    'matched_ean' => $candidate,
                    'via' => 'ean',
                ];
            }
        }

        foreach (AnanasEanLookup::candidateQueryValues($ean) as $candidate) {
            $payload = $this->getProducts(['search' => $candidate, 'page' => 0, 'size' => 5]);
            $items = $this->normalizeListPayload($payload);

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $remoteEan = trim((string) ($item['ean'] ?? ''));

                if ($remoteEan === '') {
                    continue;
                }

                foreach (AnanasEanLookup::candidateQueryValues($ean) as $wanted) {
                    if ($remoteEan === $wanted || ltrim($remoteEan, '0') === ltrim($wanted, '0')) {
                        return [
                            'product' => $item,
                            'matched_ean' => $remoteEan,
                            'via' => 'search',
                        ];
                    }
                }
            }
        }

        return $empty;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function normalizeListPayload(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['content', 'data', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], is_array(...)));
            }
        }

        return [];
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
     * @param  array<string, mixed>|list<mixed>  $body
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function postJson(string $baseUrl, string $path, array $body, string $rateCategory): mixed
    {
        return $this->requestJson('POST', $baseUrl, $path, $body, $rateCategory);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $body
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function putJson(string $baseUrl, string $path, array $body, string $rateCategory): mixed
    {
        return $this->requestJson('PUT', $baseUrl, $path, $body, $rateCategory);
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
                'Ananas API %s %s failed: HTTP %s %s',
                $method,
                $path,
                $response->status(),
                $this->truncateBody((string) $response->body()),
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
                'POST' => $request->asJson()->post($path, $data),
                'PUT' => $request->asJson()->put($path, $data),
                default => throw new RuntimeException("Ananas API client supports GET, POST and PUT only; attempted {$method}"),
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

    /**
     * @param  mixed  $payload
     * @return array<string, bool>
     */
    private function normalizeEanExistsPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $result = [];

        if (array_is_list($payload)) {
            foreach ($payload as $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($item as $ean => $exists) {
                    $result[(string) $ean] = (bool) $exists;
                }
            }

            return $result;
        }

        foreach ($payload as $ean => $exists) {
            $result[(string) $ean] = (bool) $exists;
        }

        return $result;
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

    private function truncateBody(string $body): string
    {
        $redacted = trim(self::redactSensitiveText($body));

        if ($redacted === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/', ' ', $redacted) ?? $redacted;

        return mb_substr($collapsed, 0, 500);
    }
}
