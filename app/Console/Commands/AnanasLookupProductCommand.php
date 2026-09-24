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
                            {--search= : Free-text search parameter on GET /products}
                            {--list : List merchant catalog page 0 (default when no ean/product/search)}
                            {--size=20 : Page size for --list}';

    protected $description = 'Diagnose merchant catalog visibility (GET products / basic-products, EAN variants)';

    public function handle(AnanasApiClient $client, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $this->info('Ananas merchant lookup ('.$settings->environment().')');

        $ean = is_string($this->option('ean')) ? trim($this->option('ean')) : '';
        $productIdOption = $this->option('product');
        $search = is_string($this->option('search')) ? trim($this->option('search')) : '';
        $list = (bool) $this->option('list') || ($ean === '' && $search === '' && ! (is_string($productIdOption) && $productIdOption !== ''));
        $size = max(1, min(200, (int) $this->option('size')));

        $page = $client->getProducts(['page' => 0, 'size' => $list ? $size : 1]);
        $items = $client->normalizeListPayload($page);
        $totalHint = is_array($page) && isset($page['totalElements']) ? (string) $page['totalElements'] : (string) count($items);
        $this->line('GET /products page 0: '.count($items).' row(s), totalElements='.$totalHint);

        if ($list && $ean === '' && $search === '' && ! (is_string($productIdOption) && $productIdOption !== '')) {
            if ($items === []) {
                $this->warn('Merchant catalog is empty.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->info('Merchant products (page 0):');
            $this->printProductRows($items);

            return self::SUCCESS;
        }

        if (is_string($productIdOption) && $productIdOption !== '' && $ean === '') {
            $local = Product::query()->find((int) $productIdOption);

            if ($local === null) {
                $this->error('BNC product not found.');

                return self::FAILURE;
            }

            $ean = trim((string) $local->barcode);
            $this->line('BNC #'.$local->id.' barcode: '.($ean !== '' ? $ean : '(empty)'));
            $this->line('SKU: '.($local->sku ?: 'BNC-'.$local->id));
        }

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
            $this->printProductRows($found);

            if ($found !== []) {
                return self::SUCCESS;
            }
        }

        if ($ean !== '' || $search !== '') {
            $this->newLine();
            $this->comment('If import returned 200 but GET stays empty: import job likely failed or was rejected (check productType/category for this EAN). Contact Ananas with Progress UUID from probe/import.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function printProductRows(array $rows): void
    {
        $table = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $categories = $row['categories'] ?? [];
            $categoryText = is_array($categories)
                ? implode(', ', array_map(strval(...), $categories))
                : '';

            $table[] = [
                isset($row['id']) ? (string) $row['id'] : '—',
                isset($row['ean']) ? (string) $row['ean'] : '—',
                isset($row['externalId']) ? (string) $row['externalId'] : '—',
                isset($row['productType']) ? (string) $row['productType'] : '—',
                isset($row['status']) ? (string) $row['status'] : '—',
                $categoryText !== '' ? $categoryText : '—',
            ];
        }

        if ($table === []) {
            return;
        }

        $this->table(['id', 'ean', 'externalId', 'productType', 'status', 'categories'], $table);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function printProductSummary(array $row): void
    {
        $this->printProductRows([$row]);
    }
}
