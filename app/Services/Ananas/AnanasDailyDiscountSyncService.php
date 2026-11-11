<?php

namespace App\Services\Ananas;

use App\Models\AnanasDiscountAction;
use App\Models\AnanasProductMapping;
use App\Models\AnanasSyncRun;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PriceResult;
use Carbon\Carbon;

class AnanasDailyDiscountSyncService
{
    private const TEST_INVENTORY_ID = 2568416;

    public function __construct(
        private readonly AnanasDiscountService $discountService,
        private readonly AnanasDiscountPolicy $policy,
        private readonly PriceCalculator $priceCalculator,
        private readonly AnanasSyncSettings $settings,
        private readonly AnanasCatalogWriteGuard $writeGuard,
    ) {}

    /**
     * @param  list<int>  $priceChangedInventoryIds
     * @return array{scheduled: int, cancelled: int, skipped: int, failed_ids: list<string>, errors: list<string>}
     */
    public function runDaily(
        AnanasSyncRun $run,
        AnanasSyncRunService $runService,
        array $priceChangedInventoryIds,
        bool $allowProduction = false,
        bool $dryRun = false,
    ): array {
        $exclude = array_flip($runService->takedownExcludeIds());
        $scheduled = 0;
        $cancelled = 0;
        $skipped = 0;
        $failedIds = [];
        $errors = [];
        $rows = [];
        $priceIds = [];

        $query = AnanasProductMapping::query()
            ->forCurrentRemoteEnvironment()
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->whereNotNull('ananas_product_id')
            ->whereNull('remote_missing_at')
            ->with('product');

        foreach ($query->cursor() as $mapping) {
            if (! $mapping instanceof AnanasProductMapping) {
                continue;
            }

            if ($runService->cancelRequested($runService->refreshCancel($run))) {
                break;
            }

            $inventoryId = (int) $mapping->inventoryId();

            if ($inventoryId <= 0 || $inventoryId === self::TEST_INVENTORY_ID) {
                continue;
            }

            $key = (string) $inventoryId;

            if (isset($exclude[$key])) {
                $skipped++;

                continue;
            }

            $product = $mapping->product;

            if ($product === null) {
                $skipped++;

                continue;
            }

            $pricing = $this->priceCalculator->calculate($product);
            $onBncSale = $pricing->onSale
                && round($pricing->displayPrice, 2) < round($pricing->regularPrice, 2);
            $active = AnanasDiscountAction::query()
                ->where('merchant_inventory_id', $key)
                ->where('local_status', AnanasDiscountAction::STATUS_SCHEDULED)
                ->latest('id')
                ->first();

            if (! $onBncSale && ($active === null || ! filled($active->ananas_discount_id))) {
                $skipped++;

                continue;
            }

            $priceIds[] = $inventoryId;
            $rows[] = [
                'mapping' => $mapping,
                'inventoryId' => $inventoryId,
                'onBncSale' => $onBncSale,
                'active' => $active,
                'pricing' => $pricing,
            ];
        }

        $priceIds = array_values(array_unique(array_merge(
            $priceIds,
            array_map('intval', $priceChangedInventoryIds),
        )));
        $remotePrices = $priceIds !== []
            ? $this->discountService->ananasCatalogPrices($priceIds)
            : ['current' => [], 'pending' => []];

        foreach ($rows as $row) {
            if ($runService->cancelRequested($runService->refreshCancel($run))) {
                break;
            }

            $inventoryId = $row['inventoryId'];
            $key = (string) $inventoryId;
            $pricing = $row['pricing'];
            $active = $row['active'];
            $onBncSale = $row['onBncSale'];

            if (! $onBncSale) {
                if ($active !== null && filled($active->ananas_discount_id)) {
                    if ($dryRun) {
                        $cancelled++;

                        continue;
                    }

                    try {
                        $this->writeGuard->assertAllowed($allowProduction);
                        $this->discountService->cancel((string) $active->ananas_discount_id, dryRun: false, allowProduction: $allowProduction);
                        $cancelled++;
                    } catch (\Throwable $e) {
                        $errors[] = 'Inventory '.$inventoryId.': cancel — '.$e->getMessage();
                        $failedIds[] = $key;
                    }
                } else {
                    $skipped++;
                }

                continue;
            }

            $ananasBase = round((float) ($remotePrices['current'][$inventoryId] ?? 0), 2);
            $pendingNew = round((float) ($remotePrices['pending'][$inventoryId] ?? 0), 2);

            if ($ananasBase <= 0 && $pendingNew > 0) {
                $skipped++;

                continue;
            }

            if ($ananasBase <= 0) {
                $skipped++;

                continue;
            }

            $salePrice = round($pricing->displayPrice, 2);
            $maxAllowed = $this->policy->maxDiscountPrice($ananasBase);

            if ($salePrice >= $ananasBase || $salePrice > $maxAllowed) {
                $skipped++;

                continue;
            }

            $from = Carbon::now(AnanasDailyWindow::timezone())->startOfDay();
            $to = $this->capDateTo($from, $pricing);

            if ($active !== null
                && round((float) $active->discount_price, 2) === $salePrice
                && $active->date_from?->toDateString() === $from->toDateString()
                && $active->date_to?->toDateString() === $to->toDateString()) {
                $skipped++;

                continue;
            }

            if ($active !== null && filled($active->ananas_discount_id)) {
                if ($dryRun) {
                    $cancelled++;
                } else {
                    try {
                        $this->discountService->cancel((string) $active->ananas_discount_id, dryRun: false, allowProduction: $allowProduction);
                        $cancelled++;
                    } catch (\Throwable $e) {
                        $errors[] = 'Inventory '.$inventoryId.': cancel before reschedule — '.$e->getMessage();
                        $failedIds[] = $key;

                        continue;
                    }
                }
            }

            try {
                $result = $this->discountService->schedule(
                    inventoryIds: [$inventoryId],
                    type: AnanasDiscountPolicy::TYPE_SALE,
                    percentOff: null,
                    days: $from->diffInDays($to) + 1,
                    absolutePrice: $salePrice,
                    useBncSale: false,
                    dryRun: $dryRun,
                    allowProduction: $allowProduction,
                    from: $from,
                    to: $to,
                    currency: $this->settings->discountCurrency(),
                    preloadedCatalog: $remotePrices,
                );

                $scheduled += (int) ($result['scheduled'] ?? 0);

                if (($result['failed'] ?? 0) > 0) {
                    $failedIds[] = $key;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Inventory '.$inventoryId.': schedule — '.$e->getMessage();
                $failedIds[] = $key;
            }

            $runService->heartbeat($run);
        }

        return [
            'scheduled' => $scheduled,
            'cancelled' => $cancelled,
            'skipped' => $skipped,
            'failed_ids' => array_values(array_unique($failedIds)),
            'errors' => $errors,
        ];
    }

    private function capDateTo(Carbon $from, PriceResult $pricing): Carbon
    {
        $max = $from->copy()->addDays(29);
        $saleEnd = $pricing->discount?->ends_at;

        if ($saleEnd instanceof Carbon && $saleEnd->lessThan($max)) {
            return $saleEnd->copy()->startOfDay();
        }

        return $max->copy()->startOfDay();
    }
}
