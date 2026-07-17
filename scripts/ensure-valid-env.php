#!/usr/bin/env php
<?php

/**
 * Repairs common .env issues before Laravel boots during composer install.
 * Plesk often writes ADMIN_NAME=BNC Admin (unquoted) which breaks dotenv parsing.
 */

$root = dirname(__DIR__);
$envPath = $root.'/.env';
$productionTemplate = $root.'/.env.production.example';
$defaultTemplate = $root.'/.env.example';

if (! is_file($envPath)) {
    $template = is_file($productionTemplate) ? $productionTemplate : $defaultTemplate;

    if (is_file($template)) {
        copy($template, $envPath);
        fwrite(STDERR, 'Created .env from '.basename($template).PHP_EOL);
    }

    exit(0);
}

$content = file_get_contents($envPath);
$original = $content;

// Dev-only seeder vars — must not exist in production .env
$content = preg_replace('/^ADMIN_(EMAIL|PASSWORD|NAME)=.*\R/m', '', $content);
$content = preg_replace('/^SELLER_(EMAIL|PASSWORD|NAME)=.*\R/m', '', $content);

// Quote unquoted values that contain whitespace (APP_NAME=BNC Webshop)
$content = preg_replace_callback(
    '/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/m',
    static function (array $matches): string {
        $key = $matches[1];
        $value = $matches[2];

        if ($value === '' || $value[0] === '"' || $value[0] === "'") {
            return $matches[0];
        }

        if (! preg_match('/\s/', $value)) {
            return $matches[0];
        }

        $escaped = str_replace('"', '\\"', $value);

        return $key.'="'.$escaped.'"';
    },
    $content
);

if ($content !== $original) {
    file_put_contents($envPath, $content);
    fwrite(STDERR, 'Repaired .env (removed dev seeder vars and/or quoted values with spaces)'.PHP_EOL);
}
