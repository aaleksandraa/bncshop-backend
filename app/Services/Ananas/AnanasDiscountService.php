<?php

namespace App\Services\Ananas;

use App\Models\AnanasDiscountAction;
use App\Models\AnanasProductMapping;
use App\Services\Pricing\PriceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AnanasDiscountService
{
    public function __construct(
        private readonly AnanasApiClient $apiClient,
        private readonly AnanasCatalogWriteGuard $writeGuard,
        private readonly AnanasDiscountPolicy $policy,
        private readonly PriceCalculator $priceCalculator,
        private readonly AnanasSyncSettings $settings,
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
        ?string $currency = null,
    ): array {
        $ids = $this->normalizeInventoryIds($inventoryIds);
        $type = strtoupper(trim($type));
        $currency = $this->policy->normalizeCurrency($currency ?? $this->settings->discountCurrency());
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

            if ($this->hasOverlappingScheduledAction($inventoryId, $from, $to)) {
                $skipped[] = 'Inventory '.$inventoryId.' (BNC '.$product->id.'): already has a scheduled akcija in this interval (Ananas: no overlap).';

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
                'discountPriceCurrency' => $currency,
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
     *     payloads: list<array<string, mixed>>,
     *     raw: array<string, mixed>|list<mixed>|null
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
        ?string $currency = null,
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
            currency: $currency,
        );

        if ($dryRun || $built['payloads'] === []) {
            return [
                'scheduled' => $dryRun ? count($built['payloads']) : 0,
                'failed' => 0,
                'skipped' => $built['skipped'],
                'results' => $built['rows'],
                'payloads' => $built['payloads'],
                'raw' => null,
            ];
        }

        $this->writeGuard->assertAllowed($allowProduction);

        $response = $this->apiClient->scheduleDiscounts($built['payloads'], $allowProduction);

        Log::info('Ananas discount schedule response', [
            'integration' => 'ananas',
            'inventory_ids' => array_column($built['rows'], 'merchant_inventory_id'),
            'body' => $response,
        ]);

        $parsed = $this->parseScheduleResult($response);

        if ($parsed === [] && $built['payloads'] !== []) {
            $snippet = mb_substr((string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 800);
            foreach ($built['rows'] as $index => $row) {
                $parsed[$index] = [
                    'success' => false,
                    'merchant_inventory_id' => (int) $row['merchant_inventory_id'],
                    'discount_id' => null,
                    'error' => 'Unexpected discount response: '.($snippet !== '' ? $snippet : '(empty)'),
                ];
            }
        }

        $scheduled = 0;
        $failed = 0;

        foreach ($built['rows'] as $index => $row) {
            $item = $this->resultByInventory($parsed, (int) $row['merchant_inventory_id']);
            $success = (bool) ($item['success'] ?? false);
            $discountId = $item['discount_id'] ?? null;
            $error = $item['error'] ?? ($success ? null : 'Unknown discount schedule error.');

            AnanasDiscountAction::query()->create([
                'ananas_product_mapping_id' => $row['mapping_id'],
                'merchant_inventory_id' => $row['merchant_inventory_id'],
                'ananas_discount_id' => $success ? $discountId : null,
                'discount_type' => $row['payload']['discountType'],
                'discount_price' => $row['payload']['discountPrice'],
                'currency' => $row['payload']['discountPriceCurrency'] ?? $this->policy->defaultCurrency(),
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
            'raw' => $response,
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

    private function hasOverlappingScheduledAction(int $inventoryId, Carbon $from, ?Carbon $to): bool
    {
        $end = ($to ?? $from)->toDateString();

        return AnanasDiscountAction::query()
            ->where('merchant_inventory_id', $inventoryId)
            ->where('local_status', AnanasDiscountAction::STATUS_SCHEDULED)
            ->whereDate('date_from', '<=', $end)
            ->where(function ($query) use ($from): void {
                $query->whereNull('date_to')
                    ->orWhereDate('date_to', '>=', $from->toDateString());
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{success: bool, merchant_inventory_id: int|null, discount_id: string|null, error: string|null}>
     */
    private function parseScheduleResult(array $payload): array
    {
        $rows = $this->extractScheduleRows($payload);
        $parsed = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $success = (bool) ($row['success'] ?? false);
            $data = is_array($row['data'] ?? null) ? $row['data'] : [];
            $error = $row['error'] ?? null;
            $errorBag = is_array($error) ? $error : [];
            $errorMessage = null;

            if (is_string($error) && $error !== '') {
                $errorMessage = $error;
            } elseif (isset($errorBag['errorMessage'])) {
                $errorMessage = (string) $errorBag['errorMessage'];
            } elseif (isset($row['errorMessage'])) {
                $errorMessage = (string) $row['errorMessage'];
            }

            $inventory = $data['merchantInventoryId']
                ?? $errorBag['merchantInventoryId']
                ?? $row['merchantInventoryId']
                ?? null;

            $parsed[] = [
                'success' => $success,
                'merchant_inventory_id' => $inventory !== null ? (int) $inventory : null,
                'discount_id' => isset($data['discountId'])
                    ? (string) $data['discountId']
                    : (isset($row['discountId']) ? (string) $row['discountId'] : null),
                'error' => $success ? null : ($errorMessage ?: 'Schedule failed.'),
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

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractScheduleRows(array $payload): array
    {
        foreach (['scheduleResult', 'ScheduleResult'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $this->wrapSingleResult($payload[$key]);
            }
        }

        $nested = $payload['data'] ?? null;
        if (is_array($nested) && isset($nested['scheduleResult']) && is_array($nested['scheduleResult'])) {
            return $this->wrapSingleResult($nested['scheduleResult']);
        }

        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])
            && (array_key_exists('success', $payload[0]) || isset($payload[0]['data']) || isset($payload[0]['error']))) {
            return $payload;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function wrapSingleResult(array $rows): array
    {
        if ($rows !== [] && ! array_is_list($rows)
            && (array_key_exists('success', $rows) || isset($rows['data']) || isset($rows['error']))) {
            return [$rows];
        }

        $list = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $list[] = $row;
            }
        }

        return $list;
    }
}
