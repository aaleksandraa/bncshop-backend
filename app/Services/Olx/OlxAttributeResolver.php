<?php

namespace App\Services\Olx;

use App\Models\OlxAttributeMapping;
use App\Models\OlxCategoryAttribute;
use App\Models\Product;

class OlxAttributeResolver
{
    /** @var array<int, array{attributes: \Illuminate\Support\Collection, mappings: \Illuminate\Support\Collection}> */
    private array $categoryBundleCache = [];

    public function __construct(
        private readonly OlxAttributeParser $parser,
        private readonly OlxWarrantyMapper $warrantyMapper,
        private readonly OlxAttributeNormalizer $normalizer,
        private readonly OlxOsValueNormalizer $osValueNormalizer,
        private readonly OlxProcessorValueNormalizer $processorValueNormalizer,
    ) {}

    /**
     * @return array<int, array{id: int, value: string}>
     */
    public function resolveForProduct(Product $product, int $olxCategoryId): array
    {
        $product->loadMissing(['attributeValues.attributeDefinition', 'manufacturer']);
        $parsed = $this->parser->parseFromProductText($product->name.' '.(string) $product->description);
        $bundle = $this->categoryBundle($olxCategoryId);
        $attributes = $bundle['attributes'];
        $mappings = $bundle['mappings'];

        $payload = [];

        foreach ($attributes as $olxAttributeId => $meta) {
            $value = $this->resolveSingle($product, $olxCategoryId, (int) $olxAttributeId, $meta, $mappings->get($olxAttributeId), $parsed);

            if ($value === null || $value === '') {
                continue;
            }

            $payload[] = [
                'id' => (int) $olxAttributeId,
                'value' => $value,
            ];
        }

        usort($payload, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $payload;
    }

    /**
     * @return array<int, string> olx_attribute_id => label
     */
    public function missingRequiredForPublish(Product $product, int $olxCategoryId): array
    {
        $resolved = $this->resolveForProduct($product, $olxCategoryId);
        $resolvedIds = collect($resolved)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $missing = [];
        $bundle = $this->categoryBundle($olxCategoryId);

        foreach ($bundle['attributes'] as $olxAttributeId => $meta) {
            $mapping = $bundle['mappings']->get($olxAttributeId);
            $isRequired = (bool) ($mapping?->is_required_for_publish || $meta->required);

            if (! $isRequired) {
                continue;
            }

            if (! in_array((int) $olxAttributeId, $resolvedIds, true)) {
                $missing[(int) $olxAttributeId] = (string) ($meta->display_name ?: $meta->name ?: $olxAttributeId);
            }
        }

        return $missing;
    }

    /**
     * @return array{attributes: \Illuminate\Support\Collection<int, OlxCategoryAttribute>, mappings: \Illuminate\Support\Collection<int, OlxAttributeMapping>}
     */
    private function categoryBundle(int $olxCategoryId): array
    {
        if (! isset($this->categoryBundleCache[$olxCategoryId])) {
            $this->categoryBundleCache[$olxCategoryId] = [
                'attributes' => OlxCategoryAttribute::query()
                    ->where('olx_category_id', $olxCategoryId)
                    ->get()
                    ->keyBy('olx_attribute_id'),
                'mappings' => OlxAttributeMapping::query()
                    ->where('olx_category_id', $olxCategoryId)
                    ->get()
                    ->keyBy('olx_attribute_id'),
            ];
        }

        return $this->categoryBundleCache[$olxCategoryId];
    }

    /**
     * @param  array<string, string|null>  $parsed
     */
    private function resolveSingle(
        Product $product,
        int $olxCategoryId,
        int $olxAttributeId,
        OlxCategoryAttribute $meta,
        ?OlxAttributeMapping $mapping,
        array $parsed,
    ): ?string {
        if ($mapping?->attribute_definition_id) {
            $fromLinked = $this->valueFromDefinitionId($product, (int) $mapping->attribute_definition_id, $mapping, $meta);

            if ($fromLinked !== null) {
                return $fromLinked;
            }
        }

        if ($mapping?->bnc_attribute_aliases) {
            $fromAlias = $this->valueFromAliases($product, $mapping->bnc_attribute_aliases, $mapping, $meta);

            if ($fromAlias !== null) {
                return $fromAlias;
            }
        }

        $fromOlxId = $this->valueFromOlxDefinition($product, $olxAttributeId, $mapping, $meta);

        if ($fromOlxId !== null) {
            return $fromOlxId;
        }

        $warrantyAttrId = $this->warrantyMapper->warrantyAttributeId($olxCategoryId);

        if ($warrantyAttrId === $olxAttributeId) {
            $warranty = $this->warrantyMapper->resolveForProduct($product, $olxCategoryId);

            return $warranty !== null ? $this->finalizeValue($warranty, $meta, $mapping) : null;
        }

        $fromParser = $this->valueFromParser($olxAttributeId, $parsed, $product);

        if ($fromParser !== null) {
            return $this->finalizeValue($fromParser, $meta, $mapping);
        }

        if ($mapping?->default_value) {
            return $this->finalizeValue((string) $mapping->default_value, $meta, $mapping);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function valueFromAliases(Product $product, array $aliases, ?OlxAttributeMapping $mapping, OlxCategoryAttribute $meta): ?string
    {
        $product->loadMissing(['attributeValues.attributeDefinition']);

        foreach ($product->attributeValues as $value) {
            $definition = $value->attributeDefinition?->resolveCanonical() ?? $value->attributeDefinition;
            $labels = array_filter([
                $definition?->display_name,
                $definition?->name,
            ]);

            foreach ($aliases as $alias) {
                foreach ($labels as $label) {
                    if (strcasecmp((string) $label, (string) $alias) === 0) {
                        return $this->finalizeValue(
                            trim((string) ($value->display_value ?? $value->normalized_value ?? $value->raw_value ?? '')),
                            $meta,
                            $mapping,
                            $definition?->internal_type ?? 'text',
                        );
                    }
                }
            }
        }

        return null;
    }

    private function valueFromDefinitionId(Product $product, int $definitionId, ?OlxAttributeMapping $mapping, OlxCategoryAttribute $meta): ?string
    {
        foreach ($product->attributeValues as $value) {
            $definition = $value->attributeDefinition?->resolveCanonical() ?? $value->attributeDefinition;

            if ((int) ($definition?->id) === $definitionId) {
                return $this->finalizeValue(
                    trim((string) ($value->display_value ?? $value->normalized_value ?? $value->raw_value ?? '')),
                    $meta,
                    $mapping,
                    $definition?->internal_type ?? 'text',
                );
            }
        }

        return null;
    }

    private function valueFromOlxDefinition(Product $product, int $olxAttributeId, ?OlxAttributeMapping $mapping, OlxCategoryAttribute $meta): ?string
    {
        foreach ($product->attributeValues as $value) {
            $definition = $value->attributeDefinition?->resolveCanonical() ?? $value->attributeDefinition;

            if ((int) ($definition?->olx_id) === $olxAttributeId) {
                return $this->finalizeValue(
                    trim((string) ($value->display_value ?? $value->normalized_value ?? $value->raw_value ?? '')),
                    $meta,
                    $mapping,
                    $definition?->internal_type ?? 'text',
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $parsed
     */
    private function valueFromParser(int $olxAttributeId, array $parsed, Product $product): ?string
    {
        $text = $product->name.' '.(string) $product->description;

        return match ($olxAttributeId) {
            238, 261, 5255, 5060 => $parsed['os'] ?? $parsed['smartwatch_os'],
            264, 246 => $parsed['ram'],
            4784, 4785 => $parsed['ssd_gb'],
            265, 3457, 1143, 5067 => $parsed['display_inch'],
            262, 245 => $parsed['processor_brand'],
            2465 => ($parsed['ssd_gb'] !== null && (int) $parsed['ssd_gb'] > 0) ? 'Da' : null,
            2339, 2170 => $parsed['connection'] ?? $this->parser->parseConnection($text),
            369 => $parsed['monitor_type'],
            7525 => $parsed['tv_technology'],
            3459, 6671, 2342 => $parsed['resolution'],
            3178 => $parsed['headphone_type'],
            7445 => $parsed['video_resolution'],
            7522 => $parsed['printer_type'],
            5060 => $parsed['smartwatch_os'],
            5058, 7978, 1123, 3460 => $parsed['color'],
            7126, 7164, 7167, 7153, 7204 => $parsed['listing_type'],
            3180, 273, 7446 => str_contains(strtolower($text), 'wireless') || str_contains(strtolower($text), 'bluetooth') ? 'Da' : null,
            3177, 3179, 3100, 2326 => str_contains(strtolower($text), 'gaming') ? 'Da' : null,
            1156 => $this->parseProcessorGhz($text),
            default => null,
        };
    }

    private function parseProcessorGhz(string $text): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*GHz/i', $text, $m)) {
            return str_replace(',', '.', $m[1]);
        }

        return null;
    }

    private function finalizeValue(
        string $raw,
        OlxCategoryAttribute $meta,
        ?OlxAttributeMapping $mapping,
        string $internalType = 'text',
    ): ?string {
        if (in_array((int) $meta->olx_attribute_id, [238, 261, 5255, 5060], true)) {
            $raw = $this->osValueNormalizer->normalize($raw);
        }

        if (in_array((int) $meta->olx_attribute_id, [245, 262], true)) {
            $raw = $this->processorValueNormalizer->normalize($raw);
        }

        $value = $this->normalizeValue($raw, $mapping, $internalType);

        if ($value === null || $value === '') {
            return null;
        }

        if ($meta->input_type === 'select' || ! empty($meta->options_json)) {
            $snapped = $this->normalizer->snapToSelectOption($value, $meta);

            if (! $this->normalizer->isValidOption($snapped, $meta)) {
                return null;
            }

            return $snapped;
        }

        return $value;
    }

    private function normalizeValue(string $raw, ?OlxAttributeMapping $mapping, string $internalType): ?string
    {
        if ($raw === '') {
            return null;
        }

        $mappings = $mapping?->value_mappings ?? [];

        if (isset($mappings[$raw])) {
            return (string) $mappings[$raw];
        }

        foreach ($mappings as $from => $to) {
            if (strcasecmp($raw, (string) $from) === 0) {
                return (string) $to;
            }
        }

        if ($mappings !== []) {
            $partialKeys = array_keys($mappings);
            usort($partialKeys, fn ($a, $b): int => strlen((string) $b) <=> strlen((string) $a));

            foreach ($partialKeys as $from) {
                if ($from === '' || ! is_string($from)) {
                    continue;
                }

                if (stripos($raw, $from) !== false) {
                    return (string) $mappings[$from];
                }
            }
        }

        if ($internalType === 'boolean') {
            return $this->parser->booleanToOlx(filter_var($raw, FILTER_VALIDATE_BOOLEAN) || in_array(strtolower($raw), ['da', '1', 'yes'], true));
        }

        return $raw;
    }
}
