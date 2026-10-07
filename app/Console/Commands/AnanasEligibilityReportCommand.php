<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasEligibilityReporter;
use Illuminate\Console\Command;

class AnanasEligibilityReportCommand extends Command
{
    protected $signature = 'bnc:ananas-eligibility-report
                            {--samples=8 : Example products per blocker}';

    protected $description = 'Summarize Ananas export eligibility for products in enabled category mappings';

    public function handle(AnanasEligibilityReporter $reporter): int
    {
        $summary = $reporter->summarize((int) $this->option('samples'));

        $this->info('Ananas eligibility (mapped categories only — not the whole shop)');
        $this->line('Scanned: '.$summary['total_scanned']);
        $this->line('Eligible: '.$summary['eligible']);
        $this->line('Not eligible: '.$summary['not_eligible']);

        if (($summary['barcode_shapes'] ?? []) !== []) {
            $this->newLine();
            $this->info('Barcode field shape (products.barcode):');
            $this->table(
                ['Shape', 'Count'],
                collect($summary['barcode_shapes'])
                    ->map(fn (int $count, string $shape): array => [$shape, (string) $count])
                    ->values()
                    ->all(),
            );
        }

        if ($summary['reasons'] !== []) {
            $this->newLine();
            $this->table(
                ['Reason code', 'Count'],
                collect($summary['reasons'])
                    ->map(fn (int $count, string $code): array => [$code, $count])
                    ->values()
                    ->all(),
            );
        }

        if (($summary['samples'] ?? []) !== []) {
            $this->newLine();
            $this->info('Samples per blocker:');
            foreach ($summary['samples'] as $reason => $items) {
                $this->line('  '.$reason.':');
                foreach ($items as $item) {
                    $this->line(sprintf(
                        '    #%s  barcode=%s  resolvedEan=%s  weight=%s(%s=%s)  %s',
                        $item['product_id'],
                        $item['barcode'] === null || $item['barcode'] === '' ? '—' : $item['barcode'],
                        $item['resolved_ean'] ?? '—',
                        $item['weight_status'] ?? '—',
                        $item['weight_attr'] ?? '—',
                        $item['weight_raw'] === '' || $item['weight_raw'] === null ? '—' : $item['weight_raw'],
                        $item['name'] ?? '',
                    ));
                }
            }
        }

        return self::SUCCESS;
    }
}
