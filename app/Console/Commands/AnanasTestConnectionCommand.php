<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasApiClient;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasTestConnectionCommand extends Command
{
    protected $signature = 'bnc:ananas-test-connection
                            {--force-auth : Ignore cached token and request a new one}';

    protected $description = 'Test Ananas Stage/Prod read-only API (token, product-types, warehouses, products)';

    public function handle(AnanasApiClient $client, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured. Set ANANAS_CLIENT_ID and ANANAS_CLIENT_SECRET in .env.');

            return self::FAILURE;
        }

        try {
            $this->info(sprintf(
                'Testing Ananas connection (%s environment).',
                $settings->environment(),
            ));
            $this->line('Token endpoint (POST): '.$settings->tokenEndpointUrl());
            $this->line('Products endpoint (GET): '.$settings->productBaseUrl().'/product/api/v1/merchant-integration/products');
            $this->line('Product API host: '.$settings->productBaseUrl());
            $this->line('Svc API: '.$settings->svcBaseUrl());
            $this->line('Catalog writes: '.($settings->allowCatalogWrites() ? 'enabled' : 'disabled'));

            $token = $client->authenticate((bool) $this->option('force-auth'));
            $this->info('Authentication successful (token length: '.strlen($token).', value redacted).');

            $this->runStep('Product types', function () use ($client): void {
                $productTypes = $client->getProductTypes();
                $this->info('Product types: '.count($productTypes).' returned.');
                $this->line('Sample types: '.json_encode(array_slice($productTypes, 0, 5), JSON_UNESCAPED_UNICODE));
            });

            $this->runStep('Warehouses', function () use ($client): void {
                $warehouses = $client->getWarehouses();
                $warehouseItems = is_array($warehouses['content'] ?? null) ? $warehouses['content'] : [];
                $this->info('Warehouses: '.count($warehouseItems).' returned.');
                $this->line('Warehouse sample: '.$this->redactPayload(array_slice($warehouseItems, 0, 2)));
            });

            $productItems = [];
            $this->runStep('GET products', function () use ($client, &$productItems): void {
                $products = $client->getProducts(['page' => 0, 'size' => 1]);
                $productItems = $this->normalizeListPayload($products);
                $this->info('GET products: '.count($productItems).' item(s) on page 0 (size=1).');
                $this->line('Product sample: '.$this->redactPayload(array_slice($productItems, 0, 1)));
            });

            $this->runStep('GET basic-products', function () use ($client): void {
                $basicProducts = $client->getBasicProducts(['page' => 0, 'size' => 1]);
                $basicItems = $this->normalizeListPayload($basicProducts);
                $this->info('GET basic-products: '.count($basicItems).' item(s) on page 0 (size=1).');
                $this->line('Basic product sample: '.$this->redactPayload(array_slice($basicItems, 0, 1)));
            });

            if ($productItems !== []) {
                $first = $productItems[0];
                $this->newLine();
                $this->comment('Identifier fields (first product, redacted):');
                $this->line(json_encode([
                    'id' => $first['id'] ?? null,
                    'externalId' => isset($first['externalId']) ? '[present]' : null,
                    'ean' => isset($first['ean']) ? '[present]' : null,
                    'sku' => $first['sku'] ?? null,
                    'ananasCode' => $first['ananasCode'] ?? null,
                    'groupId' => isset($first['groupId']) ? '[present]' : null,
                    'status' => $first['status'] ?? null,
                ], JSON_UNESCAPED_UNICODE));
            }

            $this->newLine();
            $this->info('Ananas read-only connection test completed. No write endpoints were called.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'HTTP 401') && $settings->isStage()) {
                $this->warn('Stage (QA2) returned 401. If Ananas confirmed credentials, they may be Production-only — retry with ANANAS_ENV=production.');
            }

            $this->error($message);

            return self::FAILURE;
        }
    }

    private function runStep(string $label, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->warn("{$label}: skipped — ".$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeListPayload(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['content', 'data', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], is_array(...)));
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $payload
     */
    private function redactPayload(array $payload): string
    {
        return AnanasApiClient::redactSensitiveText(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        );
    }
}
