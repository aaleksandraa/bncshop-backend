<?php

namespace App\Services\Olx;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OlxApiClient
{
    private const TOKEN_CACHE_KEY = 'olx_api_bearer_token';

    public function __construct(
        private readonly OlxSyncSettings $settings,
    ) {}

    public function authenticate(bool $force = false): string
    {
        if (! $force) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $credentials = $this->settings->credentials();
        $username = $credentials['username'];
        $password = $credentials['password'];

        if ($username === '' || $password === '') {
            throw new RuntimeException('OLX credentials are not configured. Unesite korisničko ime i lozinku u admin panelu (OLX → Postavke).');
        }

        $response = $this->baseRequest()
            ->post('/auth/login', [
                'username' => $username,
                'password' => $password,
                'device_name' => $credentials['device_name'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('OLX login failed: HTTP %s', $response->status()));
        }

        $token = (string) ($response->json('token') ?? '');

        if ($token === '') {
            throw new RuntimeException('OLX login returned empty token.');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addHours(23));

        return $token;
    }

    public function clearTokenCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        $payload = $this->getJson('/me');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingLimits(): array
    {
        $payload = $this->getJson('/listing-limits');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCategories(string $name): array
    {
        $payload = $this->getJson('/categories/find', ['name' => $name]);

        if ($payload === null) {
            return [];
        }

        if (isset($payload['id'])) {
            return [$payload];
        }

        return is_array($payload) ? array_values($payload) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCategory(int $categoryId): ?array
    {
        $payload = $this->getJson("/category/{$categoryId}");

        if (! is_array($payload)) {
            return null;
        }

        $data = $payload['data'] ?? $payload;

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryAttributes(int $categoryId): array
    {
        $payload = $this->getJson("/categories/{$categoryId}/attributes");
        $data = $payload['data'] ?? $payload;

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserListings(string $username, int $page = 1, int $perPage = 100): array
    {
        $payload = $this->getJson("/users/{$username}/listings", [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getListing(int $listingId): ?array
    {
        try {
            $payload = $this->getJson("/listings/{$listingId}");
        } catch (RuntimeException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createListing(array $data): array
    {
        return $this->requestJson('POST', '/listings', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateListing(int $listingId, array $data): array
    {
        return $this->requestJson('PUT', "/listings/{$listingId}", $data);
    }

    public function publishListing(int $listingId): array
    {
        return $this->requestJson('POST', "/listings/{$listingId}/publish");
    }

    public function hideListing(int $listingId): array
    {
        return $this->requestJson('POST', "/listings/{$listingId}/hide");
    }

    public function unhideListing(int $listingId): array
    {
        return $this->requestJson('POST', "/listings/{$listingId}/unhide");
    }

    public function deleteListing(int $listingId): array
    {
        return $this->requestJson('DELETE', "/listings/{$listingId}");
    }

    /**
     * @param  array<int, array{contents: string, filename: string, mime: string}>  $files
     * @return array<int, array<string, mixed>>
     */
    public function uploadListingImageFiles(int $listingId, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $this->throttle();

        $request = $this->baseRequest(
            (int) config('bnc.olx_image_upload_timeout', 60),
            0,
        )->withToken($this->authenticate());

        foreach ($files as $file) {
            $request = $request->attach(
                'images[]',
                $file['contents'],
                $file['filename'],
                ['Content-Type' => $file['mime']],
            );
        }

        $response = $request->post("/listings/{$listingId}/image-upload");

        if ($response->status() === 401) {
            $this->clearTokenCache();
            $request = $this->baseRequest(
                (int) config('bnc.olx_image_upload_timeout', 60),
                0,
            )->withToken($this->authenticate());

            foreach ($files as $file) {
                $request = $request->attach(
                    'images[]',
                    $file['contents'],
                    $file['filename'],
                    ['Content-Type' => $file['mime']],
                );
            }

            $response = $request->post("/listings/{$listingId}/image-upload");
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'OLX API POST /listings/%d/image-upload failed: HTTP %s — %s',
                $listingId,
                $response->status(),
                $response->body(),
            ));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<int, array{image_url: string}>  $images
     * @return array<int, array<string, mixed>>
     */
    public function uploadListingImages(int $listingId, array $images): array
    {
        $payload = $this->requestJson('POST', "/listings/{$listingId}/image-upload", [
            'images' => $images,
        ], (int) config('bnc.olx_image_upload_timeout', 20));

        return is_array($payload) ? $payload : [];
    }

    public function deleteListingImage(int $listingId, int $imageId): array
    {
        return $this->requestJson('POST', "/listings/{$listingId}/image-delete", [
            'imageId' => $imageId,
        ]);
    }

    public function setMainImage(int $listingId, int $imageId): array
    {
        return $this->requestJson('POST', "/listings/{$listingId}/image-main", [
            'imageId' => $imageId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function getJson(string $path, array $query = []): mixed
    {
        return $this->requestJson('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function requestJson(string $method, string $path, array $data = [], ?int $timeoutSeconds = null): mixed
    {
        $this->throttle();

        $response = $this->authorizedRequest($method, $path, $data, $timeoutSeconds, $timeoutSeconds !== null ? 0 : null);

        if ($response->status() === 401) {
            $this->clearTokenCache();
            $response = $this->authorizedRequest($method, $path, $data, $timeoutSeconds, $timeoutSeconds !== null ? 0 : null);
        }

        if (! $response->successful()) {
            Log::channel('single')->warning('OLX API error', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(sprintf(
                'OLX API %s %s failed: HTTP %s — %s',
                $method,
                $path,
                $response->status(),
                $response->body(),
            ));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizedRequest(string $method, string $path, array $data = [], ?int $timeoutSeconds = null, ?int $retries = null): Response
    {
        $token = $this->authenticate();
        $request = $this->baseRequest($timeoutSeconds, $retries)->withToken($token);

        return match (strtoupper($method)) {
            'GET' => $request->get($path, $data),
            'POST' => $request->post($path, $data),
            'PUT' => $request->put($path, $data),
            'DELETE' => $request->delete($path, $data),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };
    }

    private function baseRequest(?int $timeoutSeconds = null, ?int $retries = null): PendingRequest
    {
        $baseUrl = rtrim($this->settings->credentials()['base_url'], '/');
        $timeout = $timeoutSeconds ?? (int) config('bnc.olx_api_timeout', 60);
        $retryCount = $retries ?? (int) config('bnc.olx_api_retries', 3);

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->retry($retryCount, 1000)
            ->acceptJson()
            ->withOptions(['verify' => (bool) config('bnc.olx_api_verify_ssl', true)]);
    }

    private function throttle(): void
    {
        $ms = max(0, (int) config('bnc.olx_api_rate_limit_ms', 500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
