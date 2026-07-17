<?php

namespace App\Services\Commerce;

class InstallmentCalculator
{
    public function __construct(
        private readonly InstallmentSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->settings->calculationConfig();
    }

    public function isEnabled(): bool
    {
        $config = $this->config();

        return (bool) $config['enabled'] && $this->hasPaymentMethods();
    }

    public function hasPaymentMethods(): bool
    {
        $config = $this->config();

        return (bool) $config['mikrofin_enabled'] || (bool) $config['shopping_card_enabled'];
    }

    public function isInstallmentEligible(float $basePrice): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $config = $this->config();

        return $basePrice >= (float) $config['mikrofin_min_credit']
            && $basePrice <= (float) $config['mikrofin_max_credit'];
    }

    public function eligibilityRangeMessage(): string
    {
        $config = $this->config();
        $min = number_format((float) $config['mikrofin_min_credit'], 2, ',', '.');
        $max = number_format((float) $config['mikrofin_max_credit'], 2, ',', '.');

        return "Kupovina na rate je dostupna za ukupnu cijenu od {$min} KM do {$max} KM.";
    }

    public function isMikrofinEligible(float $basePrice): bool
    {
        return $this->isInstallmentEligible($basePrice)
            && (bool) $this->config()['mikrofin_enabled'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculatePlan(string $type, float $basePrice, int $months): ?array
    {
        if (! $this->isInstallmentEligible($basePrice)) {
            return null;
        }

        return match ($type) {
            'mikrofin' => $this->calculateMikrofinPlan($basePrice, $months),
            'shopping_card' => $this->calculateCardPlan($basePrice),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculateMikrofinPlan(float $basePrice, int $months): ?array
    {
        $config = $this->config();

        if (! $this->isMikrofinEligible($basePrice)) {
            return null;
        }

        $maxMonths = (int) $config['mikrofin_max_months'];
        $zeroInterestMaxMonths = (int) $config['mikrofin_zero_interest_max_months'];
        $minInstallment = (float) $config['min_installment'];

        if ($months < 1 || $months > $maxMonths) {
            return null;
        }

        if ($months <= $zeroInterestMaxMonths) {
            $provisionRate = (float) $config['mikrofin_provision_rate'];
            $interestRate = 0.0;
            $principal = $this->roundMoney($basePrice * (1 + $provisionRate));
        } else {
            $provisionRate = 0.0;
            $interestRate = (float) $config['mikrofin_interest_rate'];
            $principal = $basePrice;
        }

        $monthlyAmount = $this->roundMoney(
            $this->calculateAnnuity($principal, $interestRate, $months)
        );

        if ($monthlyAmount < $minInstallment) {
            return null;
        }

        $totalAmount = $this->roundMoney($monthlyAmount * $months);
        $provisionAmount = $months <= $zeroInterestMaxMonths
            ? $this->roundMoney($basePrice * $provisionRate)
            : 0.0;
        $interestAmount = $months > $zeroInterestMaxMonths
            ? $this->roundMoney($totalAmount - $basePrice)
            : 0.0;

        return [
            'type' => 'mikrofin',
            'months' => $months,
            'monthly_amount' => $monthlyAmount,
            'total_amount' => $totalAmount,
            'base_price' => $this->roundMoney($basePrice),
            'interest_rate' => $interestRate,
            'provision_rate' => $provisionRate,
            'provision_amount' => $provisionAmount,
            'interest_amount' => $interestAmount,
            'label' => "Mikrofin — {$months} mj.",
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculateCardPlan(float $basePrice): ?array
    {
        $config = $this->config();

        if (! $this->isInstallmentEligible($basePrice) || ! (bool) $config['shopping_card_enabled']) {
            return null;
        }

        $months = (int) $config['card_months'];
        $provisionRate = (float) $config['card_markup_rate'];
        $totalAmount = $this->roundMoney($basePrice * (1 + $provisionRate));
        $monthlyAmount = $this->roundMoney($totalAmount / $months);

        return [
            'type' => 'shopping_card',
            'months' => $months,
            'monthly_amount' => $monthlyAmount,
            'total_amount' => $totalAmount,
            'base_price' => $this->roundMoney($basePrice),
            'interest_rate' => 0.0,
            'provision_rate' => $provisionRate,
            'provision_amount' => $this->roundMoney($basePrice * $provisionRate),
            'interest_amount' => 0.0,
            'label' => "Shopping kartice — {$months} mj.",
        ];
    }

    private function calculateAnnuity(float $principal, float $annualRate, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }

        if ($annualRate === 0.0) {
            return $principal / $months;
        }

        $monthlyRate = $annualRate / 12;
        $factor = (1 + $monthlyRate) ** $months;

        return ($principal * ($monthlyRate * $factor)) / ($factor - 1);
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
