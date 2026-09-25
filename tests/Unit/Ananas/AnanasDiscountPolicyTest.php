<?php

namespace Tests\Unit\Ananas;

use App\Services\Ananas\AnanasDiscountPolicy;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class AnanasDiscountPolicyTest extends TestCase
{
    private AnanasDiscountPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AnanasDiscountPolicy;
        config(['bnc.ananas_discount_currency' => 'BAM']);
        Carbon::setTestNow(Carbon::create(2026, 9, 24, 12, 0, 0, 'Europe/Sarajevo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sale_payload_is_valid_for_seven_days_and_ten_percent_off(): void
    {
        $payload = $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 2566378,
            'discountPrice' => 900,
            'discountPriceCurrency' => 'RSD',
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);

        $this->assertSame(2566378, $payload['merchantInventoryId']);
        $this->assertSame('900.00', $payload['discountPrice']);
        $this->assertSame('RSD', $payload['discountPriceCurrency']);
        $this->assertSame('SALE', $payload['discountType']);
        $this->assertSame('24/09/2026', $payload['dateFrom']);
        $this->assertSame('30/09/2026', $payload['dateTo']);
    }

    public function test_omitted_currency_defaults_to_bam(): void
    {
        $payload = $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 2566378,
            'discountPrice' => 900,
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);

        $this->assertSame('BAM', $payload['discountPriceCurrency']);
    }

    public function test_km_alias_maps_to_bam(): void
    {
        $payload = $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 2566378,
            'discountPrice' => 900,
            'discountPriceCurrency' => 'KM',
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);

        $this->assertSame('BAM', $payload['discountPriceCurrency']);
    }

    public function test_sale_rejects_more_than_30_inclusive_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('30 days');

        $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 900,
            'dateFrom' => '24/09/2026',
            'dateTo' => '24/10/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);
    }

    public function test_sale_allows_30_inclusive_days(): void
    {
        $payload = $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 900,
            'dateFrom' => '24/09/2026',
            'dateTo' => '23/10/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);

        $this->assertSame('23/10/2026', $payload['dateTo']);
    }

    public function test_discount_price_must_be_at_most_95_percent_of_regular(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('95%');

        $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 980,
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);
    }

    public function test_clearance_rejects_date_to(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only dateFrom');

        $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 800,
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'CLEARANCE_SALE',
            'regularPrice' => 1000,
        ]);
    }

    public function test_clearance_allows_start_only(): void
    {
        $payload = $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 2566379,
            'discountPrice' => 800,
            'dateFrom' => '24/09/2026',
            'discountType' => 'CLEARANCE_SALE',
            'regularPrice' => 1000,
        ]);

        $this->assertArrayNotHasKey('dateTo', $payload);
        $this->assertSame('CLEARANCE_SALE', $payload['discountType']);
    }

    public function test_seasonal_sale_rejected_outside_july_or_winter_window(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SEASONAL_SALE');

        $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 800,
            'dateFrom' => '24/09/2026',
            'dateTo' => '20/10/2026',
            'discountType' => 'SEASONAL_SALE',
            'regularPrice' => 1000,
        ]);
    }

    public function test_currency_must_be_allowed_iso(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BAM, EUR, or RSD');

        $this->policy->assertValidScheduleItem([
            'merchantInventoryId' => 1,
            'discountPrice' => 800,
            'discountPriceCurrency' => 'USD',
            'dateFrom' => '24/09/2026',
            'dateTo' => '30/09/2026',
            'discountType' => 'SALE',
            'regularPrice' => 1000,
        ]);
    }
}
