<?php

namespace App\Services\Sync;

use App\Models\ApiSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

    public function login(): ApiSource
    {
        $response = $this->baseRequest()
            ->post('/api/auth/login', [
                'username' => $this->source->username,
                'password' => $this->source->password,
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

        $pageSize = $pageSize ?? $this->source->page_size ?? config('bnc.default_page_size', 500);

        $query = [
            'Page' => $page,
            'PageSize' => $pageSize,
        ];

        if ($dateModifiedAfter) {
            $query['ModifiedAfter'] = $dateModifiedAfter;
        }

        $response = $this->authenticatedRequest()
            ->get($this->integrationPath('products'), $query);

        $this->assertSuccessful($response, 'Failed to fetch products');

        $payload = $response->json();

        return [
            'data' => $this->unwrapData($payload),
            'meta' => $this->extractPagination($payload),
        ];
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
        $pageSize = $this->source->page_size ?? config('bnc.default_page_size', 500);
        $all = [];

        do {
            $response = $this->authenticatedRequest()
                ->get($this->integrationPath($resource), [
                    'Page' => $page,
                    'PageSize' => $pageSize,
                ]);

            $this->assertSuccessful($response, "Failed to fetch {$resource}");

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
            ->timeout((int) config('bnc.a1_api_timeout', 300))
            ->retry(
                (int) config('bnc.a1_api_retries', 3),
                (int) config('bnc.a1_api_retry_delay_ms', 5000),
                fn (\Throwable $exception) => $exception instanceof ConnectionException,
            )
            ->withOptions(['verify' => (bool) config('bnc.a1_api_verify_ssl', true)]);
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
