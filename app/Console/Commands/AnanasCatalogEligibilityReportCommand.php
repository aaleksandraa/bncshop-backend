<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasCatalogEligibilityReporter;
use Illuminate\Console\Command;

class AnanasCatalogEligibilityReportCommand extends Command
{
    protected $signature = 'bnc:ananas-catalog-eligibility-report
                            {--samples=3 : Example product IDs per blocker reason}';

    protected $description = 'Full-catalog Ananas eligibility report (local DB scan — no Stage API calls)';

    public function handle(AnanasCatalogEligibilityReporter $reporter): int
    {
        $this->info('Scanning entire catalog (local only — Stage API hours do not affect this)...');

        $summary = $reporter->summarize((int) $this->option('samples'));

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Total products in DB', (string) $summary['total_products']],
            ['Active + public', (string) $summary['active_public']],
            ['Eligible for Ananas', (string) $summary['eligible']],
            ['Not eligible', (string) $summary['not_eligible']],
        ]);

        if ($summary['reasons'] !== []) {
            $this->newLine();
            $this->info('Blockers (entire catalog):');
            $this->table(['Reason', 'Count'], collect($summary['reasons'])
                ->map(fn (int $count, string $code): array => [$code, $count])
                ->values()
                ->all());
        }

        if ($summary['samples'] !== []) {
            $this->newLine();
            $this->info('Sample products per blocker:');

            foreach ($summary['samples'] as $reason => $items) {
                $this->line("  {$reason}:");

                foreach ($items as $item) {
                    $barcode = $item['barcode'] ?? '—';
                    $name = mb_substr((string) $item['name'], 0, 50);
                    $this->line("    #{$item['product_id']}  barcode={$barcode}  {$name}");
                }
            }
        }

        if ($summary['eligible'] === 0) {
            $this->newLine();
            $this->warn('Zero eligible products — category probe and import are blocked until product data is fixed.');
            $this->line('Top fixes: valid 13-digit EAN in barcode field, package weight attribute, active image URL.');
            $this->line('Verify Stage API separately: php artisan bnc:ananas-test-connection');
        }

        return self::SUCCESS;
    }
}
