<?php

namespace App\Console\Commands;

use App\Services\Olx\OlxApiClient;
use Illuminate\Console\Command;

class OlxTestConnectionCommand extends Command
{
    protected $signature = 'bnc:olx-test-connection';

    protected $description = 'Test OLX API login and fetch account info';

    public function handle(OlxApiClient $client): int
    {
        try {
            $client->authenticate(true);
            $me = $client->me();
            $limits = $client->listingLimits();

            $this->info('OLX connection successful.');
            $this->line('User: '.json_encode($me, JSON_UNESCAPED_UNICODE));
            $this->line('Limits: '.json_encode($limits, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
