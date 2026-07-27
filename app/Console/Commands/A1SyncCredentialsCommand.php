<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use Illuminate\Console\Command;

class A1SyncCredentialsCommand extends Command
{
    protected $signature = 'bnc:a1-sync-credentials';

    protected $description = 'Copy A1_API_USERNAME / A1_API_PASSWORD from .env into the api_sources database row';

    public function handle(): int
    {
        $username = config('bnc.a1_api_username');
        $password = config('bnc.a1_api_password');
        $targetSystemCode = config('bnc.a1_api_target_system_code', 'bnc-shop');

        if (blank($password)) {
            $this->error('A1_API_PASSWORD is not set in .env');

            return self::FAILURE;
        }

        $source = ApiSource::query()
            ->where('target_system_code', $targetSystemCode)
            ->first();

        if ($source === null) {
            $this->error("No api_sources row with target_system_code={$targetSystemCode}. Run ApiSourceSeeder first.");

            return self::FAILURE;
        }

        $source->update([
            'username' => $username ?: 'bnc',
            'password' => $password,
            'base_url' => config('bnc.a1_api_base_url', $source->base_url),
        ]);

        $this->info("A1 credentials synced to api_sources #{$source->id} ({$source->name}).");
        $this->line('Username: '.($username ?: 'bnc'));
        $this->line('Run: php artisan bnc:a1-api-test');

        return self::SUCCESS;
    }
}
