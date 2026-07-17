<?php

require __DIR__.'/../vendor/autoload.php';

$files = [
    __DIR__.'/../.env.example',
    __DIR__.'/../.env.production.example',
];

foreach ($files as $file) {
    $name = basename($file);

    try {
        Dotenv\Dotenv::createMutable(dirname($file), $name)->load();
        echo "OK: {$name}\n";
    } catch (Throwable $e) {
        echo "FAIL: {$name} - {$e->getMessage()}\n";
        exit(1);
    }
}

echo "All env templates valid.\n";
