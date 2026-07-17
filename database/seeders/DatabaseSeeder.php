<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            SystemSettingsSeeder::class,
            EmailTemplatesSeeder::class,
            ApiSourceSeeder::class,
            MenuSeeder::class,
            B2bSeeder::class,
        ]);
    }
}
