<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'database.connections.sqlite_import' => [
        'driver' => 'sqlite',
        'database' => database_path('database.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => false,
    ],
]);

$source = DB::connection('sqlite_import');
$target = DB::connection();

if ($target->getDriverName() === 'pgsql') {
    $target->statement('SET session_replication_role = replica');
}

$tables = [
    'users',
    'roles',
    'permissions',
    'model_has_roles',
    'model_has_permissions',
    'role_has_permissions',
    'api_sources',
    'manufacturers',
    'categories',
    'category_seo',
    'attribute_definitions',
    'attribute_category_mappings',
    'suppliers',
    'tags',
    'products',
    'product_images',
    'product_attribute_values',
    'product_supplier_offers',
    'product_tags',
    'product_price_history',
    'product_sync_locks',
    'sync_diff_log',
    'seo_overrides',
    'api_import_jobs',
    'api_import_job_items',
    'system_settings',
    'shipping_rules',
    'redirects',
];

foreach ($tables as $table) {
    if (! Schema::connection('sqlite_import')->hasTable($table)) {
        echo "skip missing source table: {$table}\n";
        continue;
    }

    if (! Schema::hasTable($table)) {
        echo "skip missing target table: {$table}\n";
        continue;
    }

    $rows = $source->table($table)->get();

    if ($rows->isEmpty()) {
        echo "{$table}: 0 rows\n";
        continue;
    }

    $target->table($table)->truncate();

    foreach ($rows->chunk(200) as $chunk) {
        $target->table($table)->insert(
            $chunk->map(fn ($row) => (array) $row)->all()
        );
    }

    if ($target->getDriverName() === 'pgsql') {
        $pk = $source->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
        if ($pk && str_contains((string) $pk->sql, 'AUTOINCREMENT')) {
            $maxId = $target->table($table)->max('id') ?? 0;
            if ($maxId > 0) {
                $target->statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), ?, true)",
                    [$maxId]
                );
            }
        }
    }

    echo "{$table}: {$rows->count()} rows\n";
}

if ($target->getDriverName() === 'pgsql') {
    $target->statement('SET session_replication_role = DEFAULT');
}

echo "Done.\n";
