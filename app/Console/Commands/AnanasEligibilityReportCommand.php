<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasEligibilityReporter;
use Illuminate\Console\Command;

class AnanasEligibilityReportCommand extends Command
{
    protected $signature = 'bnc:ananas-eligibility-report
                            {--samples=8 : Example products per blocker}
                            {--include-disabled : Scan disabled mapping proposals too (data checks only; does not enable export)}';

    protected $description = 'Summarize Ananas export eligibility for products in mapped categories';

    public function handle(AnanasEligibilityReporter $reporter): int
    {
        $includeDisabled = (bool) $this->option('include-disabled');
        $summary = $reporter->summarize((int) $this->option('samples'), $includeDisabled);

        $this->info($includeDisabled
            ? 'Ananas eligibility (sva mapiranja, i isključena — nije cijeli shop)'
            : 'Ananas eligibility (samo uključena mapiranja — nije cijeli shop)');
        $this->comment($includeDisabled
            ? 'Provjera EAN / slike / težine / cijene / VAT. Ne šalje ništa na Ananas i ne uključuje export.'
            : 'Za 49 novih prijedloga dodajte --include-disabled (inače se vide samo 199/231).');
        $this->line('Scanned: '.$summary['total_scanned']);
        $this->line('Eligible: '.$summary['eligible']);
        $this->line('Not eligible: '.$summary['not_eligible']);

        if (($summary['mappings'] ?? []) !== []) {
            $this->newLine();
            $this->info('Po mapiranju:');
            $this->table(
                ['BNC', 'BNC kategorija', 'Ananas', 'On', 'SKU', 'OK', 'Top skip', 'N'],
                collect($summary['mappings'])
                    ->map(fn (array $row): array => [
                        (string) ($row['category_id'] ?? '—'),
                        (string) $row['bnc_category'],
                        (string) $row['ananas_category'],
                        $row['enabled'] ? 'yes' : 'no',
                        (string) $row['scanned'],
                        (string) $row['eligible'],
                        (string) $row['top_reason'],
                        (string) $row['top_reason_count'],
                    ])
                    ->all(),
            );
        }

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

        $this->newLine();
        $this->comment('Import i dalje ide samo iz uključenih mapiranja:');
        $this->comment('  php artisan bnc:ananas-import-products --limit=25 --dry-run');

        return self::SUCCESS;
    }
}
