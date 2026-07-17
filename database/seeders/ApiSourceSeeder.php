<?php

namespace Database\Seeders;

use App\Models\ApiSource;
use Illuminate\Database\Seeder;

class ApiSourceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedA1Source();
        $this->seedElineSource();
        $this->seedOlxSource();
    }

    private function seedA1Source(): void
    {
        $username = config('bnc.a1_api_username', 'bnc');
        $password = config('bnc.a1_api_password');
        $baseUrl = config('bnc.a1_api_base_url', 'https://a1team.ba');
        $targetSystemCode = config('bnc.a1_api_target_system_code', 'bnc-shop');

        if (! $password) {
            $this->command?->warn('A1_API_PASSWORD not set — skipping A1 ApiSource seed.');

            return;
        }

        ApiSource::query()->where('target_system_code', 'local-json')->update(['is_active' => false]);

        ApiSource::query()->updateOrCreate(
            ['target_system_code' => $targetSystemCode],
            [
                'name' => 'A1 Technoshop',
                'base_url' => $baseUrl,
                'username' => $username,
                'password' => $password,
                'page_size' => config('bnc.a1_api_page_size', 500),
                'sync_interval_minutes' => 60,
                'auto_sync_enabled' => true,
                'is_active' => true,
                'connection_status' => 'disconnected',
            ]
        );

        $this->command?->info('A1 Technoshop API source configured.');
    }

    private function seedElineSource(): void
    {
        ApiSource::query()->updateOrCreate(
            ['target_system_code' => 'eline'],
            [
                'name' => 'eLine ERP',
                'base_url' => config('bnc.eline_api_base_url'),
                'username' => null,
                'password' => null,
                'page_size' => 0,
                'sync_interval_minutes' => config('bnc.eline_sync_interval_minutes', 720),
                'auto_sync_enabled' => false,
                'is_active' => true,
                'connection_status' => 'disconnected',
            ]
        );

        $this->command?->info('eLine ERP API source configured.');
    }

    private function seedOlxSource(): void
    {
        ApiSource::query()->updateOrCreate(
            ['target_system_code' => 'olx'],
            [
                'name' => 'OLX / PIK export',
                'base_url' => config('bnc.olx_api_base_url'),
                'username' => config('bnc.olx_api_username') ?: null,
                'password' => config('bnc.olx_api_password') ?: null,
                'page_size' => 0,
                'sync_interval_minutes' => 720,
                'auto_sync_enabled' => true,
                'is_active' => true,
                'connection_status' => 'disconnected',
            ]
        );

        $this->command?->info('OLX export API source configured.');
    }
}
