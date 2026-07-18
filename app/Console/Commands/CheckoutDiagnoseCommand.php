<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class CheckoutDiagnoseCommand extends Command
{
    protected $signature = 'bnc:checkout-diagnose';

    protected $description = 'Provjeri da li je backend spreman za checkout (kod, migracije, queue)';

    public function handle(): int
    {
        $ok = true;

        $this->info('BNC checkout dijagnostika');
        $this->newLine();

        $checkoutFile = app_path('Services/Commerce/CheckoutService.php');
        $source = File::exists($checkoutFile) ? File::get($checkoutFile) : '';

        if (str_contains($source, '$rawSubtotal = $this->cartService->subtotalWithoutCoupon($cart);')) {
            $this->info('[OK] CheckoutService sadrži rawSubtotal fix');
        } else {
            $this->error('[FAIL] CheckoutService NEMA rawSubtotal fix — pokreni git pull origin main');
            $ok = false;
        }

        $requiredColumns = [
            'terms_accepted_at',
            'create_account_requested',
            'loyalty_discount_amount',
            'loyalty_reward_id',
            'pdv_number',
        ];

        foreach ($requiredColumns as $column) {
            if (Schema::hasColumn('orders', $column)) {
                $this->info("[OK] orders.{$column}");
            } else {
                $this->error("[FAIL] orders.{$column} nedostaje — pokreni: php artisan migrate --force");
                $ok = false;
            }
        }

        if (Schema::hasTable('marketing_contacts')) {
            $this->info('[OK] marketing_contacts tabela');
        } else {
            $this->warn('[WARN] marketing_contacts tabela nedostaje — marketing sync preskočen');
        }

        try {
            Queue::connection()->size();
            $this->info('[OK] Queue ('.config('queue.default').') odgovara');
        } catch (\Throwable $e) {
            $this->error('[FAIL] Queue: '.$e->getMessage());
            $ok = false;
        }

        $this->newLine();

        if ($ok) {
            $this->info('Checkout backend izgleda spremno.');
        } else {
            $this->error('Checkout backend NIJE spreman — popravi stavke iznad pa ponovo probaj narudžbu.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
