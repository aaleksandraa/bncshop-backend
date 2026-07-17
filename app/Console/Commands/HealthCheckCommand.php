<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Meilisearch\Client;

class HealthCheckCommand extends Command
{
    protected $signature = 'bnc:health';

    protected $description = 'Check Redis cache and Meilisearch connectivity';

    public function handle(): int
    {
        $ok = true;

        try {
            Cache::put('bnc:health', 'ok', 10);
            $value = Cache::get('bnc:health');
            if ($value === 'ok') {
                $this->info('Redis/cache: OK');
            } else {
                $this->error('Redis/cache: write/read failed');
                $ok = false;
            }
        } catch (\Throwable $e) {
            $this->error('Redis/cache: '.$e->getMessage());
            $ok = false;
        }

        $host = config('scout.meilisearch.host');
        $key = config('scout.meilisearch.key');

        if ($host && config('scout.driver') === 'meilisearch') {
            try {
                $client = new Client($host, $key);
                $health = $client->health();
                $this->info('Meilisearch: '.($health['status'] ?? 'unknown'));
            } catch (\Throwable $e) {
                $this->error('Meilisearch: '.$e->getMessage());
                $ok = false;
            }
        } else {
            $this->warn('Meilisearch: not configured (SCOUT_DRIVER != meilisearch)');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
