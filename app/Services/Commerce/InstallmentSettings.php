<?php

namespace App\Services\Commerce;

use App\Models\SystemSetting;
use InvalidArgumentException;

class InstallmentSettings
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->where('key', 'installments')->value('value');

        return $this->normalize(array_merge($this->defaults(), is_array($stored) ? $stored : []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Flat config consumed by InstallmentCalculator.
     *
     * @return array<string, mixed>
     */
    public function calculationConfig(): array
    {
        $settings = $this->all();

        return [
            'enabled' => (bool) ($settings['enabled'] ?? true),
            'mikrofin_enabled' => (bool) ($settings['mikrofin_enabled'] ?? true),
            'shopping_card_enabled' => (bool) ($settings['shopping_card_enabled'] ?? true),
            'mikrofin_min_credit' => (float) $settings['min_total_price'],
            'mikrofin_max_credit' => (float) $settings['max_total_price'],
            'mikrofin_max_months' => (int) $settings['mikrofin_max_months'],
            'mikrofin_zero_interest_max_months' => (int) $settings['mikrofin_zero_interest_max_months'],
            'mikrofin_provision_rate' => (float) $settings['mikrofin_provision_rate'],
            'mikrofin_interest_rate' => (float) $settings['mikrofin_interest_rate'],
            'min_installment' => (float) $settings['min_installment'],
            'card_markup_rate' => (float) $settings['card_markup_rate'],
            'card_months' => (int) $settings['card_months'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $settings = $this->all();

        return [
            'enabled' => (bool) ($settings['enabled'] ?? true),
            'min_total_price' => (float) $settings['min_total_price'],
            'max_total_price' => (float) $settings['max_total_price'],
            'min_installment' => (float) $settings['min_installment'],
            'mikrofin' => [
                'enabled' => (bool) ($settings['mikrofin_enabled'] ?? true),
                'partner_name' => (string) $settings['mikrofin_partner_name'],
                'product_name' => (string) $settings['mikrofin_product_name'],
                'max_months' => (int) $settings['mikrofin_max_months'],
                'zero_interest_max_months' => (int) $settings['mikrofin_zero_interest_max_months'],
                'provision_rate' => (float) $settings['mikrofin_provision_rate'],
                'interest_rate' => (float) $settings['mikrofin_interest_rate'],
                'description' => (string) ($settings['mikrofin_description'] ?? ''),
            ],
            'shopping_card' => [
                'enabled' => (bool) ($settings['shopping_card_enabled'] ?? true),
                'markup_rate' => (float) $settings['card_markup_rate'],
                'months' => (int) $settings['card_months'],
                'banks' => array_values($settings['shopping_card_banks'] ?? []),
                'description' => (string) ($settings['card_description'] ?? ''),
            ],
            'contact' => [
                'phone' => (string) ($settings['contact_phone'] ?? ''),
                'email' => (string) ($settings['contact_email'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $merged = $this->normalize(array_merge($this->all(), $data));

        $this->assertValid($merged);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'installments'],
            [
                'value' => $merged,
                'group' => 'checkout',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        $defaults = $this->defaults();
        $settings = array_merge($defaults, $settings);

        $settings['enabled'] = (bool) ($settings['enabled'] ?? true);
        $settings['mikrofin_enabled'] = (bool) ($settings['mikrofin_enabled'] ?? true);
        $settings['shopping_card_enabled'] = (bool) ($settings['shopping_card_enabled'] ?? true);

        if (! $settings['mikrofin_enabled'] && ! $settings['shopping_card_enabled']) {
            $settings['enabled'] = false;
        }

        $settings['min_total_price'] = round(max(0, (float) $settings['min_total_price']), 2);
        $settings['max_total_price'] = round(max(0, (float) $settings['max_total_price']), 2);
        $settings['min_installment'] = round(max(0, (float) $settings['min_installment']), 2);

        $settings['mikrofin_max_months'] = max(1, (int) $settings['mikrofin_max_months']);
        $settings['mikrofin_zero_interest_max_months'] = max(
            1,
            min((int) $settings['mikrofin_zero_interest_max_months'], $settings['mikrofin_max_months'])
        );

        $settings['mikrofin_provision_rate'] = $this->normalizeRate($settings['mikrofin_provision_rate']);
        $settings['mikrofin_interest_rate'] = $this->normalizeRate($settings['mikrofin_interest_rate']);
        $settings['card_markup_rate'] = $this->normalizeRate($settings['card_markup_rate']);

        $settings['card_months'] = max(1, (int) $settings['card_months']);

        $banks = $settings['shopping_card_banks'] ?? [];
        if (! is_array($banks)) {
            $banks = [];
        }

        $settings['shopping_card_banks'] = array_values(array_filter(array_map(
            static fn ($bank): string => trim((string) $bank),
            $banks,
        )));

        if ($settings['shopping_card_banks'] === []) {
            $settings['shopping_card_banks'] = $defaults['shopping_card_banks'];
        }

        $settings['mikrofin_partner_name'] = trim((string) ($settings['mikrofin_partner_name'] ?? 'Mikrofin'));
        $settings['mikrofin_product_name'] = trim((string) ($settings['mikrofin_product_name'] ?? 'Šoping majstor'));
        $settings['contact_phone'] = trim((string) ($settings['contact_phone'] ?? ''));
        $settings['contact_email'] = trim((string) ($settings['contact_email'] ?? ''));
        $settings['mikrofin_description'] = trim((string) ($settings['mikrofin_description'] ?? ''));
        $settings['card_description'] = trim((string) ($settings['card_description'] ?? ''));

        return $settings;
    }

    private function normalizeRate(mixed $value): float
    {
        $rate = (float) $value;

        if ($rate > 1) {
            $rate /= 100;
        }

        return round(max(0, min($rate, 1)), 4);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function assertValid(array $settings): void
    {
        if ($settings['min_total_price'] > $settings['max_total_price']) {
            throw new InvalidArgumentException('Minimalni iznos ne može biti veći od maksimalnog.');
        }

        if ($settings['mikrofin_zero_interest_max_months'] > $settings['mikrofin_max_months']) {
            throw new InvalidArgumentException('Rok bez kamate ne može biti duži od maksimalnog roka otplate.');
        }

        if ($settings['enabled'] && ! $settings['mikrofin_enabled'] && ! $settings['shopping_card_enabled']) {
            throw new InvalidArgumentException('Uključite barem jednu metodu plaćanja (Mikrofin ili shopping kartice).');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $config = config('bnc.installments', []);

        return [
            'enabled' => true,
            'min_total_price' => (float) ($config['mikrofin_min_credit'] ?? 200),
            'max_total_price' => (float) ($config['mikrofin_max_credit'] ?? 3000),
            'min_installment' => (float) ($config['min_installment'] ?? 25),
            'mikrofin_enabled' => true,
            'mikrofin_partner_name' => 'Mikrofin',
            'mikrofin_product_name' => 'Šoping majstor',
            'mikrofin_max_months' => (int) ($config['mikrofin_max_months'] ?? 36),
            'mikrofin_zero_interest_max_months' => (int) ($config['mikrofin_zero_interest_max_months'] ?? 18),
            'mikrofin_provision_rate' => (float) ($config['mikrofin_provision_rate'] ?? 0.10),
            'mikrofin_interest_rate' => (float) ($config['mikrofin_interest_rate'] ?? 0.22),
            'shopping_card_enabled' => true,
            'card_markup_rate' => (float) ($config['card_markup_rate'] ?? 0.10),
            'card_months' => (int) ($config['card_months'] ?? 24),
            'shopping_card_banks' => [
                'Raiffeisen shopping card',
                'UniCredit Classic Card',
                'Intesa shopping card',
                'NLB card',
                'Diners card',
                'American Express',
            ],
            'contact_phone' => '033 265 465',
            'contact_email' => 'prodaja@bnc.ba',
            'mikrofin_description' => '',
            'card_description' => '',
        ];
    }
}
