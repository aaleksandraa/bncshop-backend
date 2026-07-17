<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'bnc:loyalty-expire-points';

    protected $description = 'Expire loyalty points according to program settings';

    public function handle(LoyaltyService $loyaltyService): int
    {
        $result = $loyaltyService->expirePoints();

        $this->info(sprintf(
            'Expired %d point batches (%d total points).',
            $result['expired_transactions'],
            $result['expired_points'],
        ));

        return self::SUCCESS;
    }
}
