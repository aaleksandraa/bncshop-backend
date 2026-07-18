<?php

namespace App\Console\Commands;

use App\Services\Eline\ElineSyncOrchestrator;
use Illuminate\Console\Command;

class ElineTestConnectionCommand extends Command
{
    protected $signature = 'bnc:eline-test-connection';

    protected $description = 'Test eLine API connection using ELINE_* env variables';

    public function handle(ElineSyncOrchestrator $orchestrator): int
    {
        $token = (string) config('bnc.eline_api_token');

        if ($token === '') {
            $this->error('ELINE_API_TOKEN is not set in .env');

            return self::FAILURE;
        }

        $this->line('Base URL: '.config('bnc.eline_api_base_url'));
        $this->line('Shop code: '.config('bnc.eline_api_shop_code'));
        $this->line('Token: '.substr($token, 0, 4).'…'.substr($token, -4));

        try {
            $orchestrator->testConnection();
            $this->info('eLine connection OK — ArtikliZaWeb feed reachable.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('eLine connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
