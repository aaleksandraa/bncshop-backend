<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OlxIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OlxCategoryMappingSeeder::class,
            OlxAttributeMappingSeeder::class,
        ]);
    }
}
