<?php

try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', 'aleksandra', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = 'bncshop'")->fetchColumn();

    if ($exists) {
        echo "Database bncshop already exists\n";
    } else {
        $pdo->exec('CREATE DATABASE bncshop');
        echo "Database bncshop created\n";
    }
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}
