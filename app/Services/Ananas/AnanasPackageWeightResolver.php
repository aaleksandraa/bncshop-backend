<?php

namespace App\Services\Ananas;

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttributeValue;

class AnanasPackageWeightResolver
{
    /** @var array<int, string>|null */
    private ?array $chainDefinitionIdsCache = null;

    /**
     * @return list<string>
     */
    public function approvedAttributeNames(): array
    {
        return config('bnc.ananas_weight_attribute_names', []);
    }

    public function resolve(Product $product): AnanasPackageWeightResult
    {
        $product->loadMissing(['attributeValues.attributeDefinition']);

        $lastResult = AnanasPackageWeightResult::missing();

        foreach ($this->chainDefinitionIds() as $definitionId => $chainName) {
            $value = $this->firstValueForDefinition($product, (int) $definitionId);

            if ($value === null) {
                continue;
            }

            $result = $this->resolveValue($value, $chainName);

            if ($result->isOk()) {
                return $result;
            }

            $lastResult = $result;
        }

        foreach ($this->approvedAttributeNames() as $chainName) {
            foreach ($product->attributeValues as $value) {
                if (! $value instanceof ProductAttributeValue) {
                    continue;
                }

                if (! $this->valueMatchesChainName($value, $chainName)) {
                    continue;
                }

                $result = $this->resolveValue($value, $chainName);

                if ($result->isOk()) {
                    return $result;
                }

                $lastResult = $result;
            }
        }

        foreach ($product->attributeValues as $value) {
            if (! $value instanceof ProductAttributeValue) {
                continue;
            }

            if (! $this->looksLikeWeightAttribute($value)) {
                continue;
            }

            $label = $this->attributeLabel($value);
            $result = $this->resolveValue($value, $label);

            if ($result->isOk()) {
                return $result;
            }

            $lastResult = $result;
        }

        return $lastResult;
    }

    /**
     * @return list<array{
     *   definition_id: int,
     *   name: string,
     *   display_name: string|null,
     *   display_unit: string|null,
     *   in_config_chain: bool,
     *   product_value_count: int,
     *   sample_raw_values: list<string>
     * }>
     */
    public function discoverWeightAttributeDefinitions(int $sampleLimit = 5): array
    {
        $chainIds = array_map('intval', array_keys($this->chainDefinitionIds()));
        $definitions = AttributeDefinition::query()
            ->where(function ($query): void {
                foreach (['težin', 'tezin', 'weight', 'masa', 'paket'] as $needle) {
                    $query->orWhere('name', 'like', '%'.$needle.'%')
                        ->orWhere('display_name', 'like', '%'.$needle.'%');
                }
            })
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($definitions as $definition) {
            $canonicalId = $definition->resolveCanonicalId();
            $valueCount = ProductAttributeValue::query()
                ->whereIn('attribute_definition_id', AttributeDefinition::expandedDefinitionIds($canonicalId))
                ->whereHas('product', fn ($q) => $q->where('is_public', true)->where('status', 'active'))
                ->count();

            $samples = ProductAttributeValue::query()
                ->whereIn('attribute_definition_id', AttributeDefinition::expandedDefinitionIds($canonicalId))
                ->where('raw_value', '!=', '')
                ->orderByDesc('id')
                ->limit($sampleLimit)
                ->pluck('raw_value')
                ->map(fn ($raw): string => mb_substr(trim((string) $raw), 0, 80))
                ->unique()
                ->values()
                ->all();

            $rows[] = [
                'definition_id' => $canonicalId,
                'name' => (string) $definition->resolveCanonical()->name,
                'display_name' => $definition->resolveCanonical()->display_name,
                'display_unit' => $definition->resolveCanonical()->display_unit,
                'in_config_chain' => in_array($canonicalId, $chainIds, true)
                    || in_array((int) $definition->id, $chainIds, true),
                'product_value_count' => $valueCount,
                'sample_raw_values' => $samples,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['product_value_count'] <=> $a['product_value_count']);

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function summarizeParseStatusesForActiveProducts(int $scanLimit = 0): array
    {
        $summary = [
            AnanasPackageWeightResult::STATUS_OK => 0,
            AnanasPackageWeightResult::STATUS_MISSING => 0,
            AnanasPackageWeightResult::STATUS_UNPARSEABLE => 0,
            AnanasPackageWeightResult::STATUS_ZERO_OR_NEGATIVE => 0,
            AnanasPackageWeightResult::STATUS_UNITLESS_AMBIGUOUS => 0,
        ];

        $query = Product::query()
            ->where('is_public', true)
            ->where('status', 'active')
            ->with(['attributeValues.attributeDefinition'])
            ->orderBy('id');

        if ($scanLimit > 0) {
            $query->limit($scanLimit);
        }

        $query->chunkById(200, function ($products) use (&$summary): void {
            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }

                $status = $this->resolve($product)->parseStatus;
                $summary[$status] = ($summary[$status] ?? 0) + 1;
            }
        });

        ksort($summary);

        return $summary;
    }

    private function resolveValue(ProductAttributeValue $value, string $sourceAttributeName): AnanasPackageWeightResult
    {
        $definition = $value->attributeDefinition?->resolveCanonical();
        $displayUnit = filled($definition?->display_unit) ? trim((string) $definition->display_unit) : null;

        $raw = $this->normalizeWhitespace((string) $value->raw_value);
        $normalized = trim((string) ($value->normalized_value ?? ''));

        $candidates = [];

        if ($raw !== '') {
            $candidates[] = $raw;
        }

        if ($normalized !== '' && $displayUnit !== null) {
            $candidates[] = trim($normalized.' '.$displayUnit);
        }

        if ($raw !== '' && $displayUnit !== null && ! preg_match('/\d\s*(g|gram|grams|kg|kilogram|kilograms)\b/iu', $raw)) {
            $numeric = $this->parseDecimal($raw);

            if ($numeric !== null) {
                $candidates[] = trim($raw.' '.$displayUnit);
            }
        }

        if ($normalized !== '' && $displayUnit === null && $raw === '') {
            $candidates[] = $normalized;
        }

        $candidates = array_values(array_unique($candidates));

        $lastResult = AnanasPackageWeightResult::missing();

        foreach ($candidates as $candidate) {
            $result = $this->parseRawValue($candidate, $sourceAttributeName);

            if ($result->isOk()) {
                return $result;
            }

            if ($result->parseStatus !== AnanasPackageWeightResult::STATUS_MISSING) {
                $lastResult = $result;
            }
        }

        return $lastResult;
    }

    private function parseRawValue(string $raw, string $sourceAttributeName): AnanasPackageWeightResult
    {
        if ($this->looksLikeCapacity($raw)) {
            return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Value resembles capacity, not package weight.');
        }

        if (preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*(g|gram|grams|kg|kilogram|kilograms)\s*$/iu', $raw, $matches)) {
            $numeric = $this->parseDecimal($matches[1]);
            $unit = strtolower($matches[2]);

            if ($numeric === null) {
                return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Malformed numeric portion.');
            }

            $kg = str_starts_with($unit, 'g') ? $numeric / 1000 : $numeric;

            return $this->finalizeKg($kg, $sourceAttributeName, $raw);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(g|gram|grams|kg|kilogram|kilograms)\b/iu', $raw, $matches)) {
            $numeric = $this->parseDecimal($matches[1]);
            $unit = strtolower($matches[2]);

            if ($numeric !== null) {
                $kg = str_starts_with($unit, 'g') ? $numeric / 1000 : $numeric;

                return $this->finalizeKg($kg, $sourceAttributeName, $raw);
            }
        }

        if (preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*$/u', $raw)) {
            return AnanasPackageWeightResult::unitlessAmbiguous($sourceAttributeName, $raw);
        }

        return AnanasPackageWeightResult::unparseable($sourceAttributeName, $raw, 'Unrecognized weight format.');
    }

    private function finalizeKg(float $kg, string $sourceAttributeName, string $raw): AnanasPackageWeightResult
    {
        if ($kg <= 0) {
            return AnanasPackageWeightResult::zeroOrNegative($sourceAttributeName, $raw);
        }

        return AnanasPackageWeightResult::ok(round($kg, 6), $sourceAttributeName, $raw);
    }

    private function looksLikeCapacity(string $raw): bool
    {
        return (bool) preg_match('/maksimalna\s+težina|težina\s+kartona|težina\s+plastike|ambalaž/i', $raw);
    }

    private function looksLikeWeightAttribute(ProductAttributeValue $value): bool
    {
        $label = mb_strtolower($this->attributeLabel($value));

        if ($label === '') {
            return false;
        }

        if (preg_match('/maksimalna\s+težina|težina\s+kartona|težina\s+plastike|nosivost|kapacitet/i', $label)) {
            return false;
        }

        return (bool) preg_match('/težin|tezin|weight|\bmasa\b|paket/i', $label);
    }

    private function valueMatchesChainName(ProductAttributeValue $value, string $chainName): bool
    {
        $snapshot = trim((string) $value->attribute_name_snapshot);
        $definition = $value->attributeDefinition?->resolveCanonical();

        return strcasecmp($snapshot, $chainName) === 0
            || strcasecmp((string) ($definition?->name ?? ''), $chainName) === 0
            || strcasecmp((string) ($definition?->display_name ?? ''), $chainName) === 0;
    }

    private function attributeLabel(ProductAttributeValue $value): string
    {
        $definition = $value->attributeDefinition?->resolveCanonical();

        if (filled($definition?->display_name)) {
            return (string) $definition->display_name;
        }

        if (filled($definition?->name)) {
            return (string) $definition->name;
        }

        return trim((string) $value->attribute_name_snapshot);
    }

    private function parseDecimal(string $value): ?float
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function normalizeWhitespace(string $value): string
    {
        $value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int, string> definition_id => chain label
     */
    private function chainDefinitionIds(): array
    {
        if ($this->chainDefinitionIdsCache !== null) {
            return $this->chainDefinitionIdsCache;
        }

        $ordered = [];
        $names = $this->approvedAttributeNames();

        foreach ($names as $name) {
            $definitions = AttributeDefinition::query()
                ->where(function ($query) use ($name): void {
                    $query->where('name', $name)
                        ->orWhere('display_name', $name);
                })
                ->get();

            foreach ($definitions as $definition) {
                foreach (AttributeDefinition::expandedDefinitionIds($definition->resolveCanonicalId()) as $definitionId) {
                    $ordered[(int) $definitionId] = $name;
                }
            }
        }

        AttributeDefinition::query()
            ->where(function ($query): void {
                foreach (['težin', 'tezin', 'weight', 'masa'] as $needle) {
                    $query->orWhere('name', 'like', '%'.$needle.'%')
                        ->orWhere('display_name', 'like', '%'.$needle.'%');
                }
            })
            ->orderBy('name')
            ->each(function (AttributeDefinition $definition) use (&$ordered): void {
                $label = $definition->resolveCanonical()->publicLabel();

                if (preg_match('/maksimalna\s+težina|težina\s+kartona|težina\s+plastike|nosivost|kapacitet/i', $label)) {
                    return;
                }

                foreach (AttributeDefinition::expandedDefinitionIds($definition->resolveCanonicalId()) as $definitionId) {
                    $ordered[(int) $definitionId] ??= $label;
                }
            });

        $this->chainDefinitionIdsCache = $ordered;

        return $this->chainDefinitionIdsCache;
    }

    private function firstValueForDefinition(Product $product, int $definitionId): ?ProductAttributeValue
    {
        foreach ($product->attributeValues as $value) {
            if ((int) $value->attribute_definition_id === $definitionId) {
                return $value;
            }

            $definition = $value->attributeDefinition;

            if ($definition !== null && $definition->resolveCanonicalId() === $definitionId) {
                return $value;
            }
        }

        return null;
    }
}
