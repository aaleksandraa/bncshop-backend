<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureFreshConnections
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->ensureDatabaseConnection();
        $this->ensureRedisConnection();

        return $next($request);
    }

    private function ensureDatabaseConnection(): void
    {
        if (! config('database.default')) {
            return;
        }

        try {
            DB::connection()->select('SELECT 1');
        } catch (Throwable) {
            DB::purge();
            DB::reconnect();
        }
    }

    private function ensureRedisConnection(): void
    {
        if (config('cache.default') !== 'redis' && config('session.driver') !== 'redis') {
            return;
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            Redis::purge('default');

            if (config('cache.default') === 'redis') {
                Redis::purge('cache');
            }
        }
    }
}
