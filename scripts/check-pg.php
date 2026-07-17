<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'database.connections.pgsql_test' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'bncshop',
        'username' => 'postgres',
        'password' => 'aleksandra',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],
]);

try {
    $tables = Illuminate\Support\Facades\DB::connection('pgsql_test')
        ->select("select table_name from information_schema.tables where table_schema = 'public' order by table_name");

    echo 'PG connected, tables: '.count($tables).PHP_EOL;

    $hasProducts = collect($tables)->contains(fn ($t) => $t->table_name === 'products');

    if ($hasProducts) {
        $count = Illuminate\Support\Facades\DB::connection('pgsql_test')->table('products')->count();
        echo 'products: '.$count.PHP_EOL;
    } else {
        echo 'products table: missing'.PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}
