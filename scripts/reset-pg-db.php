<?php

try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', 'aleksandra', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = \'bncshop\' AND pid <> pg_backend_pid()');
    $pdo->exec('DROP DATABASE IF EXISTS bncshop');
    $pdo->exec('CREATE DATABASE bncshop');
    echo "Database bncshop recreated\n";
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    exit(1);
}
