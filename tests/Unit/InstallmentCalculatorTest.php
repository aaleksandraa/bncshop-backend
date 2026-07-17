<?php

namespace Tests\Unit;

use App\Services\Commerce\InstallmentCalculator;
use App\Services\Commerce\InstallmentSettings;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class InstallmentCalculatorTest extends TestCase
{
    /** @return InstallmentSettings&MockObject */
    private function mockSettings(array $overrides = []): InstallmentSettings
    {
        $defaults = [
            'enabled' => true,
            'mikrofin_enabled' => true,
            'shopping_card_enabled' => true,
            'mikrofin_min_credit' => 200.0,
            'mikrofin_max_credit' => 3000.0,
            'mikrofin_max_months' => 36,
            'mikrofin_zero_interest_max_months' => 18,
            'mikrofin_provision_rate' => 0.10,
            'mikrofin_interest_rate' => 0.22,
            'min_installment' => 25.0,
            'card_markup_rate' => 0.10,
            'card_months' => 24,
        ];

        /** @var InstallmentSettings&MockObject $settings */
        $settings = $this->createMock(InstallmentSettings::class);
        $settings->method('calculationConfig')->willReturn(array_merge($defaults, $overrides));

        return $settings;
    }

    public function test_eligibility_boundaries(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings());

        $this->assertFalse($calculator->isInstallmentEligible(199.0));
        $this->assertTrue($calculator->isInstallmentEligible(200.0));
        $this->assertTrue($calculator->isInstallmentEligible(3000.0));
        $this->assertFalse($calculator->isInstallmentEligible(3000.01));
    }

    public function test_eligibility_requires_at_least_one_payment_method(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings([
            'mikrofin_enabled' => false,
            'shopping_card_enabled' => false,
        ]));

        $this->assertFalse($calculator->isEnabled());
        $this->assertFalse($calculator->isInstallmentEligible(500.0));
    }

    public function test_mikrofin_zero_interest_plan_applies_provision(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings());
        $plan = $calculator->calculateMikrofinPlan(1000.0, 18);

        $this->assertNotNull($plan);
        $this->assertEqualsWithDelta(1100.0, $plan['total_amount'], 0.05);
        $this->assertEqualsWithDelta(61.11, $plan['monthly_amount'], 0.01);
        $this->assertSame(0.0, $plan['interest_rate']);
        $this->assertSame(0.10, $plan['provision_rate']);
    }

    public function test_mikrofin_interest_zone_applies_annual_rate(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings());
        $plan = $calculator->calculateMikrofinPlan(1000.0, 19);

        $this->assertNotNull($plan);
        $this->assertSame(0.22, $plan['interest_rate']);
        $this->assertSame(0.0, $plan['provision_rate']);
        $this->assertGreaterThan(1100.0, $plan['total_amount']);
    }

    public function test_mikrofin_plan_rejected_when_monthly_below_minimum(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings());

        $this->assertNull($calculator->calculateMikrofinPlan(200.0, 18));
        $this->assertNotNull($calculator->calculateMikrofinPlan(200.0, 8));
    }

    public function test_shopping_card_plan_uses_markup_and_fixed_months(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings());
        $plan = $calculator->calculateCardPlan(1000.0);

        $this->assertNotNull($plan);
        $this->assertSame(24, $plan['months']);
        $this->assertSame(1100.0, $plan['total_amount']);
        $this->assertSame(45.83, $plan['monthly_amount']);
    }

    public function test_eligibility_range_message_uses_configured_limits(): void
    {
        $calculator = new InstallmentCalculator($this->mockSettings([
            'mikrofin_min_credit' => 250.0,
            'mikrofin_max_credit' => 2500.0,
        ]));

        $this->assertSame(
            'Kupovina na rate je dostupna za ukupnu cijenu od 250,00 KM do 2.500,00 KM.',
            $calculator->eligibilityRangeMessage(),
        );
    }

    public function test_settings_normalize_disables_master_when_both_methods_are_off(): void
    {
        $settings = new InstallmentSettings();

        $reflection = new \ReflectionClass($settings);
        $method = $reflection->getMethod('normalize');
        $method->setAccessible(true);

        /** @var array<string, mixed> $normalized */
        $normalized = $method->invoke($settings, [
            'enabled' => true,
            'mikrofin_enabled' => false,
            'shopping_card_enabled' => false,
        ]);

        $this->assertFalse($normalized['enabled']);
    }

    public function test_settings_reject_enabled_without_any_payment_method(): void
    {
        $settings = new InstallmentSettings();

        $reflection = new \ReflectionClass($settings);
        $assertValid = $reflection->getMethod('assertValid');
        $assertValid->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Uključite barem jednu metodu plaćanja');

        $assertValid->invoke($settings, [
            'enabled' => true,
            'mikrofin_enabled' => false,
            'shopping_card_enabled' => false,
            'min_total_price' => 200.0,
            'max_total_price' => 3000.0,
            'mikrofin_zero_interest_max_months' => 18,
            'mikrofin_max_months' => 36,
        ]);
    }
}
