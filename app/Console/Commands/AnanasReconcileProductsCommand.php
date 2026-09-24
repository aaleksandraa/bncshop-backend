<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasProductReconciliationService;
use App\Services\Ananas\AnanasSyncSettings;
use Illuminate\Console\Command;

class AnanasReconcileProductsCommand extends Command
{
    protected $signature = 'bnc:ananas-reconcile-products
                            {--limit=100 : Max local mappings to reconcile}
                            {--ean= : Reconcile a single EAN}
                            {--include-linked : Also refresh already LINKED mappings}';

    protected $description = 'Reconcile submitted Ananas product mappings via GET /products and refresh category validation';

    public function handle(
        AnanasProductReconciliationService $reconciliationService,
        AnanasSyncSettings $settings,
    ): int {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $eanOption = $this->option('ean');
        $ean = is_string($eanOption) && $eanOption !== '' ? $eanOption : null;

        $this->info('Ananas product reconciliation ('.$settings->environment().')');

        $result = $reconciliationService->reconcileSubmittedMappings(
            limit: (int) $this->option('limit'),
            ean: $ean,
            includeLinked: (bool) $this->option('include-linked'),
        );

        $this->line('Linked: '.$result['linked']);
        $this->line('Pending: '.$result['pending']);
        $this->line('Failed: '.$result['failed']);

        foreach ($result['details'] as $detail) {
            $this->line(' - '.$detail);
        }

        if ($result['pending'] > 0) {
            $this->newLine();
            $this->comment('GET /products je merchant lista i kasni za POST import. Sačekajte par minuta pa ponovite reconcile.');
            $this->comment('Novi EAN-ovi mogu ostati pending dok Ananas ne uradi onboarding.');
        }

        return self::SUCCESS;
    }
}
