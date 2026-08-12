<?php

namespace App\Services\Sync;

use App\Models\ApiSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IntegrationApiClient
{
    private const TOKEN_REFRESH_BUFFER_SECONDS = 60;

    public function __construct(
        private readonly ApiSource $source,
    ) {}

    public static function forSource(ApiSource $source): self
    {
        return new self($source);
    }

    public static function formatModifiedAfter(?Carbon $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public function login(): ApiSource
    {
        [$username, $password] = $this->resolveCredentials();

        if (blank($username) || blank($password)) {
            throw new RuntimeException(
                'A1 API kredencijali nisu postavljeni. Unesite ih u admin panelu (API izvori) i sačuvajte, '
                .'ili postavite A1_API_USERNAME / A1_API_PASSWORD u .env pa pokrenite: php artisan bnc:a1-sync-credentials'
            );
        }

        $response = $this->baseRequest()
            ->post('/api/auth/login', [
                'username' => $username,
                'password' => $password,
            ]);

        $this->assertSuccessful($response, 'Login failed');

        $this->persistTokens($response->json());

        return $this->source->fresh();
    }

    public function refreshToken(): ApiSource
    {
        if (! $this->source->refresh_token) {
            return $this->login();
        }

        try {
            $response = $this->baseRequest()
                ->withToken($this->source->access_token)
                ->post('/api/auth/refresh', [
                    'refresh_token' => $this->source->refresh_token,
                ]);

            if ($response->failed()) {
                return $this->login();
            }

            $this->persistTokens($response->json());

            return $this->source->fresh();
        } catch (Throwable) {
            return $this->login();
        }
    }

    public function ensureAuthenticated(): void
    {
        $this->source->refresh();

        if (! $this->source->access_token) {
            $this->login();

            return;
        }

        if ($this->source->token_expires_at && $this->source->token_expires_at->copy()->subSeconds(self::TOKEN_REFRESH_BUFFER_SECONDS)->isPast()) {
            $this->refreshToken();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        return $this->fetchAllPages('categories');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttributes(): array
    {
        return $this->fetchAllPages('attributes');
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getProducts(?string $dateModifiedAfter = null, int $page = 1, ?int $pageSize = null): array
    {
        $this->ensureAuthenticated();

        $initialPageSize = $this->resolvePageSize($pageSize);
        $lastException = null;

        foreach ($this->adaptivePageSizes($initialPageSize) as $tryPageSize) {
            $query = [
                'Page' => $page,
                'PageSize' => $tryPageSize,
            ];

            if ($dateModifiedAfter) {
                $query['ModifiedAfter'] = $dateModifiedAfter;
            }

            try {
                $response = $this->getWithRetry(
                    $this->integrationPath('products'),
                    $query,
                    'Failed to fetch products',
                );

                $payload = $response->json();

                return [
                    'data' => $this->unwrapData($payload),
                    'meta' => $this->extractPagination($payload),
                    'page_size' => $tryPageSize,
                ];
            } catch (RuntimeException $e) {
                $lastException = $e;

                if (! $this->isGatewayTimeoutError($e)) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Failed to fetch products: retries exhausted');
    }

    public function resolvedPageSize(?int $pageSize = null): int
    {
        return $this->resolvePageSize($pageSize);
    }

    public function testConnection(): bool
    {
        try {
            $this->login();
            $this->getProducts(null, 1, 1);

            $this->source->update([
                'connection_status' => 'connected',
                'last_error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->source->update([
                'connection_status' => 'disconnected',
                'last_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPages(string $resource): array
    {
        $this->ensureAuthenticated();

        $page = 1;
        $pageSize = $this->resolvePageSize(null);
        $all = [];

        do {
            $response = $this->getWithRetry(
                $this->integrationPath($resource),
                [
                    'Page' => $page,
                    'PageSize' => $pageSize,
                ],
                "Failed to fetch {$resource}",
            );

            $payload = $response->json();
            $all = array_merge($all, $this->unwrapData($payload));
            $pagination = $this->extractPagination($payload);
            $page = $this->resolveNextPage($pagination, $page, count($this->unwrapData($payload)), $pageSize);
        } while ($page !== null);

        return $all;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractPagination(array $payload): array
    {
        return $payload['pagination'] ?? $payload['meta'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $pagination
     */
    private function resolveNextPage(array $pagination, int $currentPage, int $recordsOnPage, int $pageSize): ?int
    {
        if (array_key_exists('nextPage', $pagination)) {
            $next = $pagination['nextPage'];

            if ($next === null || $next === '' || (int) $next <= $currentPage) {
                return null;
            }

            return (int) $next;
        }

        if ($recordsOnPage >= $pageSize) {
            return $currentPage + 1;
        }

        return null;
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->source->base_url, '/'))
            ->acceptJson()
            ->timeout((int) config('bnc.a1_api_timeout', 120))
            ->withOptions(['verify' => (bool) config('bnc.a1_api_verify_ssl', true)]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function getWithRetry(string $path, array $query, string $errorMessage): Response
    {
        $maxAttempts = max(1, (int) config('bnc.a1_api_retries', 3));
        $delayMs = max(0, (int) config('bnc.a1_api_retry_delay_ms', 5000));
        $lastBody = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->authenticatedRequest()->get($path, $query);

                if ($response->successful()) {
                    return $response;
                }

                $lastBody = $response->body();

                if ($this->shouldRetryResponse($response) && $attempt < $maxAttempts) {
                    usleep($delayMs * 1000 * $attempt);

                    continue;
                }

                $this->assertSuccessful($response, $errorMessage);
            } catch (ConnectionException $e) {
                if ($attempt < $maxAttempts) {
                    usleep($delayMs * 1000 * $attempt);

                    continue;
                }

                throw new RuntimeException(sprintf('%s: %s', $errorMessage, $e->getMessage()), 0, $e);
            }
        }

        throw new RuntimeException(sprintf(
            '%s: %s',
            $errorMessage,
            $lastBody ?: 'retries exhausted',
        ));
    }

    private function shouldRetryResponse(Response $response): bool
    {
        return in_array($response->status(), [408, 429, 500, 502, 503, 504], true);
    }

    private function resolvePageSize(?int $pageSize): int
    {
        $resolved = $pageSize
            ?? $this->source->page_size
            ?? config('bnc.a1_api_page_size', config('bnc.default_page_size', 100));

        $maxPageSize = max(1, (int) config('bnc.a1_api_max_page_size', 50));

        return min(max(1, (int) $resolved), $maxPageSize);
    }

    /**
     * @return list<int>
     */
    private function adaptivePageSizes(int $requested): array
    {
        $candidates = array_unique([
            $requested,
            (int) config('bnc.a1_api_incremental_page_size', 25),
            25,
            10,
        ]);

        $sizes = [];

        foreach ($candidates as $size) {
            if ($size >= 1 && $size <= $requested) {
                $sizes[] = $size;
            }
        }

        rsort($sizes);

        return array_values($sizes);
    }

    private function isGatewayTimeoutError(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '504 Gateway Time-out')
            || str_contains($message, '502 Bad Gateway')
            || str_contains($message, '503 Service Temporarily Unavailable')
            || str_contains($message, '504 Gateway Timeout');
    }

    private function authenticatedRequest(): PendingRequest
    {
        return $this->baseRequest()->withToken($this->source->access_token);
    }

    private function integrationPath(string $resource): string
    {
        return sprintf(
            '/api/integrations/%s/%s',
            $this->source->target_system_code,
            $resource
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveCredentials(): array
    {
        $username = $this->source->username;
        $password = $this->source->password;

        if ($this->source->usesIntegrationApiImport()) {
            if (blank($username)) {
                $username = config('bnc.a1_api_username');
            }

            if (blank($password)) {
                $password = config('bnc.a1_api_password');
            }
        }

        return [$username, $password];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistTokens(array $payload): void
    {
        $accessToken = $payload['accessToken'] ?? $payload['access_token'] ?? null;
        $refreshToken = $payload['refreshToken'] ?? $payload['refresh_token'] ?? $this->source->refresh_token;
        $expiresIn = $payload['expiresIn'] ?? $payload['expires_in'] ?? 3600;

        if (! $accessToken) {
            throw new RuntimeException('API login response missing access token.');
        }

        $this->source->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => now()->addSeconds((int) $expiresIn),
            'connection_status' => 'connected',
            'last_error' => null,
        ]);

        $this->source->refresh();
    }

    private function assertSuccessful(Response $response, string $message): void
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf('%s: %s', $message, $response->body()));
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    private function unwrapData(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return is_array($payload) ? $payload : [];
    }
}
