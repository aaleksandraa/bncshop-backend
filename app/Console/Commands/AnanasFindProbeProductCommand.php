<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Services\Ananas\AnanasCategoryProbeService;
use Illuminate\Console\Command;

class AnanasFindProbeProductCommand extends Command
{
    protected $signature = 'bnc:ananas-find-probe-product
                            {mapping : Ananas category mapping ID}
                            {--limit=500 : Max products to scan}';

    protected $description = 'Find an eligible BNC product for Ananas category probe and show blocker summary';

    public function handle(AnanasCategoryProbeService $probeService): int
    {
        $mappingId = (int) $this->argument('mapping');
        $mapping = AnanasCategoryMapping::query()->with('category')->find($mappingId);

        if ($mapping === null) {
            $this->error("Category mapping #{$mappingId} not found.");

            return self::FAILURE;
        }

        $diagnosis = $probeService->diagnoseProbeCandidates($mapping, (int) $this->option('limit'));

        $this->info('Probe product search for mapping #'.$mappingId);
        $this->line('BNC category: '.($mapping->category?->name ?? $mapping->category_id));
        $this->line('Scoped category IDs: '.implode(', ', $diagnosis['category_ids']));
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Total products in scoped categories', (string) $diagnosis['total_in_scope']],
            ['Active + public', (string) $diagnosis['active_public']],
            ['Eligible for probe', (string) $diagnosis['eligible']],
        ]);

        if ($diagnosis['reasons'] !== []) {
            $this->newLine();
            $this->info('Blockers (sampled active/public products):');
            $this->table(
                ['Reason', 'Count'],
                collect($diagnosis['reasons'])->map(fn (int $count, string $code): array => [$code, $count])->values()->all(),
            );
        }

        if ($diagnosis['first_eligible_product_id'] !== null) {
            $productId = $diagnosis['first_eligible_product_id'];
            $this->newLine();
            $this->info("First eligible product ID: {$productId}");
            $this->line("Dry-run probe with explicit product:");
            $this->line("  php artisan bnc:ananas-probe-category {$mappingId} --product={$productId} --dry-run");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('No eligible product found in scanned sample.');
        $this->line('Common fixes: add EAN (barcode), active image URL, package weight attribute, positive price.');

        return self::FAILURE;
    }
}
