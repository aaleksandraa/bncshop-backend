<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasEligibilityReporter;
use Illuminate\Console\Command;

class AnanasEligibilityReportCommand extends Command
{
    protected $signature = 'bnc:ananas-eligibility-report';

    protected $description = 'Summarize Ananas export eligibility for products in enabled category mappings';

    public function handle(AnanasEligibilityReporter $reporter): int
    {
        $summary = $reporter->summarize();

        $this->info('Ananas eligibility report');
        $this->line('Scanned: '.$summary['total_scanned']);
        $this->line('Eligible: '.$summary['eligible']);
        $this->line('Not eligible: '.$summary['not_eligible']);

        if ($summary['reasons'] !== []) {
            $this->newLine();
            $this->table(['Reason code', 'Count'], collect($summary['reasons'])
                ->map(fn (int $count, string $code): array => [$code, $count])
                ->values()
                ->all());
        }

        return self::SUCCESS;
    }
}
