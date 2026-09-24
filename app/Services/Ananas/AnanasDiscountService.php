<?php

namespace App\Services\Ananas;

use App\Models\AnanasDiscountAction;
use App\Models\AnanasProductMapping;
use App\Services\Pricing\PriceCalculator;
use Carbon\Carbon;
use InvalidArgumentException;

class AnanasDiscountService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasCatalogWriteGuard $writeGuard,
        private readonly AnanasDiscountPolicy $policy,
        private readonly PriceCalculator $priceCalculator,
    ) {}

    /**
     * Build SALE/SEASONAL/CLEARANCE payloads for linked merchant inventory ids.
     *
     * @param  list<int>  $inventoryIds
     * @return array{
     *     payloads: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>,
     *     skipped: list<string>
     * }
     */
    public function buildSchedule(
        array $inventoryIds,
        string $type = AnanasDiscountPolicy::TYPE_SALE,
        ?int $percentOff = 10,
        int $days = 7,
        ?float $absolutePrice = null,
        bool $useBncSale = true,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $ids = $this->normalizeInventoryIds($inventoryIds);
        $type = strtoupper(trim($type));
        $from = ($from ?? Carbon::now(config('app.timezone', 'Europe/Sarajevo')))->copy()->startOfDay();

        if ($type !== AnanasDiscountPolicy::TYPE_CLEARANCE_SALE) {
            $to = ($to ?? $from->copy()->addDays(max(1, $days) - 1))->copy()->startOfDay();
        } else {
            $to = null;
        }

        $payloads = [];
        $rows = [];
        $skipped = [];

        foreach ($ids as $inventoryId) {
            $mapping = $this->findLinkedMapping($inventoryId);

            if ($mapping === null) {
                $skipped[] = 'Inventory '.$inventoryId.': no LINKED local mapping.';

                continue;
            }

            $product = $mapping->product;

            if ($product === null) {
                $skipped[] = 'Inventory '.$inventoryId.': mapping has no BNC product.';

                continue;
            }

            $pricing = $this->priceCalculator->calculate($product);
            $regular = round($pricing->regularPrice, 2);

            try {
                $discountPrice = $this->resolveDiscountPrice(
                    regular: $regular,
                    display: round($pricing->displayPrice, 2),
                    onSale: $pricing->onSale,
                    percentOff: $percentOff,
                    absolutePrice: $absolutePrice,
                    useBncSale: $useBncSale,
                );
            } catch (InvalidArgumentException $e) {
                $skipped[] = 'Inventory '.$inventoryId.' (BNC '.$product->id.'): '.$e->getMessage();

                continue;
            }

            $candidate = [
                'merchantInventoryId' => $inventoryId,
                'discountPrice' => $discountPrice,
                'discountPriceCurrency' => AnanasDiscountPolicy::CURRENCY_RSD,
                'dateFrom' => $this->policy->formatApiDate($from),
                'discountType' => $type,
                'regularPrice' => $regular,
            ];

            if ($to !== null) {
                $candidate['dateTo'] = $this->policy->formatApiDate($to);
            }

            try {
                $payload = $this->policy->assertValidScheduleItem($candidate);
            } catch (InvalidArgumentException $e) {
                $skipped[] = 'Inventory '.$inventoryId.': '.$e->getMessage();

                continue;
            }

            $payloads[] = $payload;
            $rows[] = [
                'mapping_id' => (int) $mapping->id,
                'product_id' => (int) $product->id,
                'ean' => (string) $mapping->ean,
                'merchant_inventory_id' => $inventoryId,
                'remote_status' => (string) ($mapping->remote_status ?: ''),
                'regular_price' => $regular,
                'discount_price' => (float) $payload['discountPrice'],
                'payload' => $payload,
            ];
        }

        return compact('payloads', 'rows', 'skipped');
    }

    /**
     * @param  list<int>  $inventoryIds
     * @return array{
     *     scheduled: int,
     *     failed: int,
     *     skipped: list<string>,
     *     results: list<array<string, mixed>>,
     *     payloads: list<array<string, mixed>>
     * }
     */
    public function schedule(
        array $inventoryIds,
        string $type = AnanasDiscountPolicy::TYPE_SALE,
        ?int $percentOff = 10,
        int $days = 7,
        ?float $absolutePrice = null,
        bool $useBncSale = true,
        bool $dryRun = true,
        bool $allowProduction = false,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $built = $this->buildSchedule(
            inventoryIds: $inventoryIds,
            type: $type,
            percentOff: $percentOff,
            days: $days,
            absolutePrice: $absolutePrice,
            useBncSale: $useBncSale,
            from: $from,
            to: $to,
        );

        if ($dryRun || $built['payloads'] === []) {
            return [
                'scheduled' => $dryRun ? count($built['payloads']) : 0,
                'failed' => 0,
                'skipped' => $built['skipped'],
                'results' => $built['rows'],
                'payloads' => $built['payloads'],
            ];
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $response = $this->apiClient->scheduleDiscounts($built['payloads'], $allowProduction);
        $parsed = $this->parseScheduleResult($response);
        $scheduled = 0;
        $failed = 0;

        foreach ($built['rows'] as $index => $row) {
            $item = $parsed[$index] ?? $this->resultByInventory($parsed, (int) $row['merchant_inventory_id']);
            $success = (bool) ($item['success'] ?? false);
            $discountId = $item['discount_id'] ?? null;
            $error = $item['error'] ?? ($success ? null : 'Unknown discount schedule error.');

            AnanasDiscountAction::query()->create([
                'ananas_product_mapping_id' => $row['mapping_id'],
                'merchant_inventory_id' => $row['merchant_inventory_id'],
                'ananas_discount_id' => $success ? $discountId : null,
                'discount_type' => $row['payload']['discountType'],
                'discount_price' => $row['payload']['discountPrice'],
                'currency' => AnanasDiscountPolicy::CURRENCY_RSD,
                'date_from' => $this->policy->parseApiDate($row['payload']['dateFrom'], 'dateFrom'),
                'date_to' => isset($row['payload']['dateTo'])
                    ? $this->policy->parseApiDate($row['payload']['dateTo'], 'dateTo')
                    : null,
                'local_status' => $success
                    ? AnanasDiscountAction::STATUS_SCHEDULED
                    : AnanasDiscountAction::STATUS_FAILED,
                'last_error' => $error,
                'request_payload' => $row['payload'],
                'response_payload' => $item,
            ]);

            if ($success) {
                $scheduled++;
            } else {
                $failed++;
            }

            $built['rows'][$index]['success'] = $success;
            $built['rows'][$index]['discount_id'] = $discountId;
            $built['rows'][$index]['error'] = $error;
        }

        return [
            'scheduled' => $scheduled,
            'failed' => $failed,
            'skipped' => $built['skipped'],
            'results' => $built['rows'],
            'payloads' => $built['payloads'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRemote(Carbon $from, Carbon $to): array
    {
        return $this->apiClient->getDiscounts(
            $this->policy->formatApiDate($from),
            $this->policy->formatApiDate($to),
        );
    }

    /**
     * @return array{cancelled: bool, raw: array<string, mixed>}
     */
    public function cancel(string $discountId, bool $dryRun = true, bool $allowProduction = false): array
    {
        $id = trim($discountId);

        if ($id === '') {
            throw new InvalidArgumentException('discountId is required.');
        }

        if ($dryRun) {
            return ['cancelled' => true, 'raw' => ['discountId' => $id, 'dry_run' => true]];
        }

        $this->writeGuard->assertAllowed($allowProduction);
        $raw = $this->apiClient->cancelDiscount($id, $allowProduction);

        AnanasDiscountAction::query()
            ->where('ananas_discount_id', $id)
            ->update([
                'local_status' => AnanasDiscountAction::STATUS_CANCELLED,
                'last_error' => null,
            ]);

        return ['cancelled' => true, 'raw' => $raw];
    }

    /**
     * Inventory ids for LINKED products that are READY_FOR_PUBLISH or PUBLISHED.
     *
     * @param  list<int>  $inventoryIds
     * @return list<int>
     */
    public function actionableInventoryIds(array $inventoryIds = [], int $limit = 25): array
    {
        $query = AnanasProductMapping::query()
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->whereIn('remote_status', ['READY_FOR_PUBLISH', 'PUBLISHED'])
            ->orderByDesc('last_success_at');

        $wanted = $this->normalizeInventoryIds($inventoryIds);

        if ($wanted !== []) {
            $query->where(function ($inner) use ($wanted): void {
                $inner->whereIn('merchant_inventory_id', $wanted)
                    ->orWhereIn('ananas_product_id', array_map('strval', $wanted));
            });
        }

        return $query
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AnanasProductMapping $mapping): int => $mapping->inventoryId())
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    public function normalizeInventoryIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
    }

    private function resolveDiscountPrice(
        float $regular,
        float $display,
        bool $onSale,
        ?int $percentOff,
        ?float $absolutePrice,
        bool $useBncSale,
    ): float {
        if ($regular <= 0) {
            throw new InvalidArgumentException('BNC regular price is missing.');
        }

        if ($absolutePrice !== null) {
            return round($absolutePrice, 2);
        }

        if ($useBncSale && $onSale && $display > 0 && $display < $regular) {
            return round($display, 2);
        }

        $percent = $percentOff ?? 10;

        if ($percent < 5) {
            throw new InvalidArgumentException('Ananas requires at least 5% off regular price (--percent=5 or more).');
        }

        return round($regular * (1 - ($percent / 100)), 2);
    }

    private function findLinkedMapping(int $inventoryId): ?AnanasProductMapping
    {
        return AnanasProductMapping::query()
            ->with('product')
            ->where('local_status', AnanasProductMapping::LOCAL_LINKED)
            ->where(function ($query) use ($inventoryId): void {
                $query->where('merchant_inventory_id', (string) $inventoryId)
                    ->orWhere('ananas_product_id', (string) $inventoryId);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{success: bool, merchant_inventory_id: int|null, discount_id: string|null, error: string|null}>
     */
    private function parseScheduleResult(array $payload): array
    {
        $rows = $payload['scheduleResult'] ?? $payload;
        $parsed = [];

        if (! is_array($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $success = (bool) ($row['success'] ?? false);
            $data = is_array($row['data'] ?? null) ? $row['data'] : [];
            $error = is_array($row['error'] ?? null) ? $row['error'] : [];

            $parsed[] = [
                'success' => $success,
                'merchant_inventory_id' => isset($data['merchantInventoryId'])
                    ? (int) $data['merchantInventoryId']
                    : (isset($error['merchantInventoryId']) ? (int) $error['merchantInventoryId'] : null),
                'discount_id' => isset($data['discountId']) ? (string) $data['discountId'] : null,
                'error' => $success ? null : (string) ($error['errorMessage'] ?? 'Schedule failed.'),
            ];
        }

        return $parsed;
    }

    /**
     * @param  list<array{success: bool, merchant_inventory_id: int|null, discount_id: string|null, error: string|null}>  $parsed
     * @return array{success: bool, merchant_inventory_id: int|null, discount_id: string|null, error: string|null}
     */
    private function resultByInventory(array $parsed, int $inventoryId): array
    {
        foreach ($parsed as $row) {
            if ((int) ($row['merchant_inventory_id'] ?? 0) === $inventoryId) {
                return $row;
            }
        }

        return [
            'success' => false,
            'merchant_inventory_id' => $inventoryId,
            'discount_id' => null,
            'error' => 'No matching scheduleResult row.',
        ];
    }
}
