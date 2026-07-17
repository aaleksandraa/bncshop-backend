<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Meilisearch\Client as MeilisearchClient;

class HealthController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'meilisearch' => $this->checkMeilisearch(),
            'cache_tags' => $this->checkCacheTags(),
        ];

        $healthy = collect($checks)->every(fn (array $check): bool => $check['ok']);

        if (! $this->shouldExposeDetailedChecks()) {
            return $this->success([
                'status' => $healthy ? 'ok' : 'degraded',
            ], status: $healthy ? 200 : 503);
        }

        return $this->success([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], status: $healthy ? 200 : 503);
    }

    private function shouldExposeDetailedChecks(): bool
    {
        if (config('app.debug')) {
            return true;
        }

        $user = auth()->user();

        return $user !== null && $user->can('manage_sync');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            $this->logCheckFailure('database', $e);

            return ['ok' => false, 'message' => $this->publicMessage('unavailable')];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            $this->logCheckFailure('redis', $e);

            return ['ok' => false, 'message' => $this->publicMessage('unavailable')];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkMeilisearch(): array
    {
        try {
            $host = config('scout.meilisearch.host');

            if (! $host) {
                return ['ok' => false, 'message' => $this->publicMessage('not configured')];
            }

            $key = config('scout.meilisearch.key');
            $client = new MeilisearchClient($host, $key);
            $client->health();

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            $this->logCheckFailure('meilisearch', $e);

            return ['ok' => false, 'message' => $this->publicMessage('unavailable')];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkCacheTags(): array
    {
        $supportsTags = $this->productReadCache->supportsTags();

        return [
            'ok' => $supportsTags,
            'message' => $supportsTags ? 'enabled' : 'disabled — set CACHE_STORE=redis',
        ];
    }

    private function publicMessage(string $message): string
    {
        return config('app.debug') ? $message : 'unavailable';
    }

    private function logCheckFailure(string $service, \Throwable $e): void
    {
        Log::warning("Health check failed for {$service}", [
            'message' => $e->getMessage(),
        ]);
    }
}
