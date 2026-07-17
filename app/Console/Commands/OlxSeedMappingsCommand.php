<?php

namespace App\Console\Commands;

use Database\Seeders\OlxIntegrationSeeder;
use Illuminate\Console\Command;

class OlxSeedMappingsCommand extends Command
{
    protected $signature = 'bnc:olx-seed-mappings
                            {--categories-only : Samo mapiranje kategorija}
                            {--attributes-only : Samo mapiranje atributa (laptop)}';

    protected $description = 'Seed OLX category mappings (i laptop attribute aliasi) u admin panel';

    public function handle(): int
    {
        if ($this->option('attributes-only')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\OlxAttributeMappingSeeder', '--force' => true]);

            return self::SUCCESS;
        }

        if ($this->option('categories-only')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\OlxCategoryMappingSeeder', '--force' => true]);

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => OlxIntegrationSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
