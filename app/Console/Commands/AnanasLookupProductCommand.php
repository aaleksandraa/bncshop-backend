<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Ananas\AnanasApiClient;
use App\Services\Ananas\AnanasEanLookup;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasLookupProductCommand extends Command
{
    protected $signature = 'bnc:ananas-lookup-product
                            {--ean= : Barcode to look up on merchant GET /products}
                            {--product= : BNC product id (uses barcode + sku/externalId search)}
                            {--search= : Free-text search parameter on GET /products}';

    protected $description = 'Diagnose merchant catalog visibility (GET products / basic-products, EAN variants)';

    public function handle(AnanasApiClient $client, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $this->info('Ananas merchant lookup ('.$settings->environment().')');

        $page = $client->getProducts(['page' => 0, 'size' => 1]);
        $items = $client->normalizeListPayload($page);
        $totalHint = is_array($page) && isset($page['totalElements']) ? (string) $page['totalElements'] : '?';
        $this->line('GET /products page 0: '.count($items).' row(s), totalElements='.$totalHint);

        $ean = is_string($this->option('ean')) ? trim($this->option('ean')) : '';

        $productIdOption = $this->option('product');

        if ($ean === '' && is_string($productIdOption) && $productIdOption !== '') {
            $local = Product::query()->find((int) $productIdOption);

            if ($local === null) {
                $this->error('BNC product not found.');

                return self::FAILURE;
            }

            $ean = trim((string) $local->barcode);
            $this->line('BNC #'.$local->id.' barcode: '.($ean !== '' ? $ean : '(empty)'));
            $this->line('SKU: '.($local->sku ?: 'BNC-'.$local->id));
        }

        $search = is_string($this->option('search')) ? trim($this->option('search')) : '';

        if ($ean !== '') {
            $this->newLine();
            $this->line('EAN query candidates: '.implode(', ', AnanasEanLookup::candidateQueryValues($ean)));

            $detailed = $client->findProductByEanDetailed($ean);

            if ($detailed['product'] !== null) {
                $this->info('Found via '.$detailed['via'].' (stored EAN: '.($detailed['matched_ean'] ?? '?').')');
                $this->printProductSummary($detailed['product']);

                return self::SUCCESS;
            }

            $this->warn('Not found via ean/search variants on GET /products.');
        }

        if ($search !== '') {
            $this->newLine();
            $this->line('Trying search='.$search);
            $payload = $client->getProducts(['search' => $search, 'page' => 0, 'size' => 10]);
            $found = $client->normalizeListPayload($payload);
            $this->line('Matches: '.count($found));

            foreach ($found as $row) {
                if (is_array($row)) {
                    $this->printProductSummary($row);
                }
            }

            if ($found !== []) {
                return self::SUCCESS;
            }
        }

        if ($ean === '' && $search === '') {
            $this->line('Pass --ean=, --product=, or --search= for a targeted lookup.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('If import returned 200 but GET stays empty: job may have failed (wrong productType/category for this EAN), or QA2 delay. Contact Ananas with Progress UUID from probe/import.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function printProductSummary(array $row): void
    {
        $categories = $row['categories'] ?? [];

        if (! is_array($categories)) {
            $categories = [];
        }

        $this->table(['Field', 'Value'], [
            ['id', isset($row['id']) ? (string) $row['id'] : '—'],
            ['ean', isset($row['ean']) ? (string) $row['ean'] : '—'],
            ['sku', isset($row['sku']) ? (string) $row['sku'] : '—'],
            ['externalId', isset($row['externalId']) ? (string) $row['externalId'] : '—'],
            ['productType', isset($row['productType']) ? (string) $row['productType'] : '—'],
            ['status', isset($row['status']) ? (string) $row['status'] : '—'],
            ['categories', $categories !== [] ? implode(', ', array_map(strval(...), $categories)) : '—'],
        ]);
    }
}
