<?php

namespace App\Console\Commands;

use App\Services\Ananas\AnanasDiscountService;
use App\Services\Ananas\AnanasSyncSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnanasListDiscountsCommand extends Command
{
    protected $signature = 'bnc:ananas-list-discounts
                            {--from= : Start dd/MM/yyyy (default today)}
                            {--to= : End dd/MM/yyyy (default +31 days)}';

    protected $description = 'GET Ananas payment discounts for a date interval';

    public function handle(AnanasDiscountService $discountService, AnanasSyncSettings $settings): int
    {
        if (! $settings->hasCredentials()) {
            $this->error('Ananas credentials are not configured.');

            return self::FAILURE;
        }

        $tz = config('app.timezone', 'Europe/Sarajevo');
        $fromOption = trim((string) $this->option('from'));
        $toOption = trim((string) $this->option('to'));
        $from = $fromOption === ''
            ? Carbon::now($tz)->startOfDay()
            : Carbon::createFromFormat('d/m/Y', $fromOption, $tz);
        $to = $toOption === ''
            ? $from->copy()->addDays(31)
            : Carbon::createFromFormat('d/m/Y', $toOption, $tz);

        if ($from === false || $to === false) {
            $this->error('Dates must be dd/MM/yyyy.');

            return self::FAILURE;
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $this->info('Ananas discounts ('.$settings->environment().') '.$from->format('d/m/Y').' → '.$to->format('d/m/Y'));

        try {
            $rows = $discountService->listRemote($from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('No discounts in this interval.');

            return self::SUCCESS;
        }

        $this->table(
            ['discountId', 'merchantInventoryId', 'discountPrice', 'dateFrom', 'dateTo'],
            array_map(static fn (array $row): array => [
                (string) ($row['discountId'] ?? $row['discounts'] ?? '—'),
                (string) ($row['merchantInventoryId'] ?? '—'),
                (string) ($row['discountPrice'] ?? '—'),
                (string) ($row['dateFrom'] ?? '—'),
                (string) ($row['dateTo'] ?? $row['dataTo'] ?? '—'),
            ], $rows),
        );

        return self::SUCCESS;
    }
}
