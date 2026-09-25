<?php

namespace App\Services\Ananas;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class AnanasDiscountPolicy
{
    public const TYPE_SALE = 'SALE';

    public const TYPE_SEASONAL_SALE = 'SEASONAL_SALE';

    public const TYPE_CLEARANCE_SALE = 'CLEARANCE_SALE';

    public const CURRENCY_BAM = 'BAM';

    public const CURRENCY_EUR = 'EUR';

    public const CURRENCY_RSD = 'RSD';

    /** Docs: discount price can be reduced to 95% of regular (minimum 5% off). */
    public const MAX_DISCOUNT_PRICE_RATIO = 0.95;

    public const MAX_SALE_DAYS_INCLUSIVE = 31;

    public const MAX_SEASONAL_DAYS_INCLUSIVE = 60;

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_SALE, self::TYPE_SEASONAL_SALE, self::TYPE_CLEARANCE_SALE];
    }

    /**
     * @param  array{
     *     merchantInventoryId: int,
     *     discountPrice: float|string,
     *     discountPriceCurrency?: string,
     *     dateFrom: string,
     *     dateTo?: string|null,
     *     discountType: string,
     *     regularPrice?: float|null
     * }  $item
     * @return array<string, mixed>
     */
    public function assertValidScheduleItem(array $item): array
    {
        $inventoryId = (int) ($item['merchantInventoryId'] ?? 0);
        $type = strtoupper(trim((string) ($item['discountType'] ?? '')));
        $currency = $this->normalizeCurrency(
            isset($item['discountPriceCurrency']) ? (string) $item['discountPriceCurrency'] : $this->defaultCurrency(),
        );
        $price = $this->normalizePrice($item['discountPrice'] ?? null);
        $dateFrom = $this->parseApiDate((string) ($item['dateFrom'] ?? ''), 'dateFrom');
        $dateToRaw = $item['dateTo'] ?? null;
        $dateTo = is_string($dateToRaw) && trim($dateToRaw) !== ''
            ? $this->parseApiDate(trim($dateToRaw), 'dateTo')
            : null;
        $regular = isset($item['regularPrice']) && $item['regularPrice'] !== null
            ? round((float) $item['regularPrice'], 2)
            : null;

        if ($inventoryId <= 0) {
            throw new InvalidArgumentException('merchantInventoryId must be a positive Ananas inventory id.');
        }

        if (! in_array($type, self::types(), true)) {
            throw new InvalidArgumentException('discountType must be SALE, SEASONAL_SALE, or CLEARANCE_SALE.');
        }

        if ($price <= 0) {
            throw new InvalidArgumentException('discountPrice must be greater than 0.');
        }

        if ($type === self::TYPE_CLEARANCE_SALE) {
            if ($dateTo !== null) {
                throw new InvalidArgumentException('CLEARANCE_SALE must send only dateFrom (no dateTo).');
            }
        } else {
            if ($dateTo === null) {
                throw new InvalidArgumentException($type.' requires dateTo (dd/MM/yyyy).');
            }

            if ($dateTo->lt($dateFrom)) {
                throw new InvalidArgumentException('Discount start date can not be after end date.');
            }

            $inclusiveDays = $dateFrom->copy()->startOfDay()->diffInDays($dateTo->copy()->startOfDay()) + 1;
            $maxDays = $type === self::TYPE_SALE
                ? self::MAX_SALE_DAYS_INCLUSIVE
                : self::MAX_SEASONAL_DAYS_INCLUSIVE;

            if ($inclusiveDays > $maxDays) {
                throw new InvalidArgumentException(sprintf(
                    '%s duration must not exceed %d days (got %d inclusive).',
                    $type,
                    $maxDays,
                    $inclusiveDays,
                ));
            }
        }

        if ($type === self::TYPE_SEASONAL_SALE && ! $this->isValidSeasonalStart($dateFrom)) {
            throw new InvalidArgumentException(
                'SEASONAL_SALE must begin between 1 Jul & 15 Jul (summer) or 25 Dec & 10 Jan (winter).',
            );
        }

        if ($regular !== null && $regular > 0) {
            $maxDiscountPrice = round($regular * self::MAX_DISCOUNT_PRICE_RATIO, 2);

            if ($price > $maxDiscountPrice) {
                throw new InvalidArgumentException(sprintf(
                    'discountPrice %.2f must be at most 95%% of regular %.2f (max %.2f).',
                    $price,
                    $regular,
                    $maxDiscountPrice,
                ));
            }

            if ($price >= $regular) {
                throw new InvalidArgumentException('discountPrice must be lower than the regular (base) price.');
            }
        }

        $payload = [
            'merchantInventoryId' => $inventoryId,
            'discountPrice' => number_format($price, 2, '.', ''),
            'discountPriceCurrency' => $currency,
            'dateFrom' => $dateFrom->format('d/m/Y'),
            'discountType' => $type,
        ];

        if ($dateTo !== null) {
            $payload['dateTo'] = $dateTo->format('d/m/Y');
        }

        return $payload;
    }

    public function formatApiDate(CarbonInterface $date): string
    {
        return $date->copy()->timezone(config('app.timezone', 'Europe/Sarajevo'))->format('d/m/Y');
    }

    public function parseApiDate(string $value, string $field): Carbon
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException($field.' is required (dd/MM/yyyy).');
        }

        $date = Carbon::createFromFormat('d/m/Y', $trimmed, config('app.timezone', 'Europe/Sarajevo'));

        if ($date === false) {
            throw new InvalidArgumentException($field.' must be dd/MM/yyyy.');
        }

        return $date->startOfDay();
    }

    public function isValidSeasonalStart(CarbonInterface $date): bool
    {
        $month = (int) $date->month;
        $day = (int) $date->day;

        if ($month === 7 && $day >= 1 && $day <= 15) {
            return true;
        }

        if ($month === 12 && $day >= 25) {
            return true;
        }

        return $month === 1 && $day <= 10;
    }

    public function maxDiscountPrice(float $regularPrice): float
    {
        return round($regularPrice * self::MAX_DISCOUNT_PRICE_RATIO, 2);
    }

    /**
     * @return list<string>
     */
    public static function allowedCurrencies(): array
    {
        return [self::CURRENCY_BAM, self::CURRENCY_EUR, self::CURRENCY_RSD];
    }

    /**
     * Merchant inventory currency. Docs list RSD-only; QA2 rejected RSD for BNC
     * (BAM import basePrice). Never convert amounts.
     */
    public function defaultCurrency(): string
    {
        return $this->normalizeCurrency((string) config('bnc.ananas_discount_currency', self::CURRENCY_BAM));
    }

    public function normalizeCurrency(?string $value): string
    {
        $currency = strtoupper(trim((string) $value));

        if ($currency === '' || $currency === 'KM') {
            $currency = self::CURRENCY_BAM;
        }

        if (! in_array($currency, self::allowedCurrencies(), true)) {
            throw new InvalidArgumentException(
                'discountPriceCurrency must be BAM, EUR, or RSD (merchant inventory currency; no FX).',
            );
        }

        return $currency;
    }

    private function normalizePrice(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return round((float) $value, 2);
    }
}
