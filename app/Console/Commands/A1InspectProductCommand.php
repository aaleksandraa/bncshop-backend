<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\Sync\IntegrationApiClient;
use Illuminate\Console\Command;

class A1InspectProductCommand extends Command
{
    protected $signature = 'bnc:a1-inspect-product
                            {product : BNC product ID or A1 UUID}';

    protected $description = 'Compare one BNC product against live A1 integration attributes (weight specs)';

    public function handle(): int
    {
        $arg = trim((string) $this->argument('product'));
        $product = ctype_digit($arg)
            ? Product::query()->with('attributeValues.attributeDefinition')->find((int) $arg)
            : Product::query()->with('attributeValues.attributeDefinition')->where('external_product_id', $arg)->first();

        if ($product === null) {
            $this->error('BNC product not found: '.$arg);

            return self::FAILURE;
        }

        $source = ApiSource::query()
            ->where('is_active', true)
            ->where('target_system_code', config('bnc.a1_api_target_system_code', 'bnc-shop'))
            ->first();

        if ($source === null) {
            $this->error('No active A1 API source.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'BNC #%d  %s  source=%s  uuid=%s',
            $product->id,
            $product->name,
            (string) ($product->import_source ?: '—'),
            (string) ($product->external_product_id ?: '—'),
        ));

        $this->newLine();
        $this->info('BNC specs:');
        $this->table(
            ['Attribute', 'raw_value'],
            $product->attributeValues->map(function ($value): array {
                $definition = $value->attributeDefinition;

                return [
                    (string) ($definition?->display_name ?: $definition?->name ?: $value->attribute_name_snapshot ?: '—'),
                    (string) ($value->raw_value ?: '—'),
                ];
            })->all() ?: [['—', 'no specs']],
        );

        $uuid = (string) $product->external_product_id;

        if ($uuid === '') {
            $this->warn('No external_product_id — cannot query A1.');

            return self::SUCCESS;
        }

        try {
            $remote = IntegrationApiClient::forSource($source)->getProductById($uuid);
        } catch (\Throwable $e) {
            $this->error('Live A1 fetch failed: '.$e->getMessage());
            $this->comment('A1 list API is paginated; many shops have no GET /products/{id}. BNC already stores Bruto/Neto when A1 sends them on the product list payload.');

            return self::SUCCESS;
        }

        $remoteAttrs = is_array($remote['attributes'] ?? null) ? array_values($remote['attributes']) : [];
        $this->newLine();
        $this->info('Live A1 attributes ('.count($remoteAttrs).'):');

        if ($remoteAttrs !== [] && is_array($remoteAttrs[0] ?? null)) {
            $this->comment('A1 attribute object keys: '.implode(', ', array_keys($remoteAttrs[0])));
        }

        $rows = [];
        $a1Weight = null;

        foreach ($remoteAttrs as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $this->resolveA1AttributeName($row);
            $value = $this->resolveA1AttributeValue($row);
            $isWeight = preg_match('/težin|tezin|weight|masa|bruto|neto/i', $name) === 1;

            $rows[] = [$name, $value, $isWeight ? 'yes' : 'no'];

            if ($isWeight && $a1Weight === null) {
                $a1Weight = [$name, $value];
            }
        }

        $this->table(
            ['A1 name (resolved)', 'value', 'Weight-like'],
            $rows !== [] ? $rows : [['—', 'A1 sent no attributes[]', '—']],
        );

        if ($a1Weight === null) {
            $this->warn('A1 live payload has no weight attribute for this SKU. BNC cannot invent it.');
        } else {
            $this->info('A1 HAS weight: '.$a1Weight[0].' = '.$a1Weight[1]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveA1AttributeName(array $row): string
    {
        foreach (['attributeName', 'AttributeName', 'name', 'Name', 'displayName', 'display_name'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
        }

        if (isset($row['attribute']) && is_array($row['attribute'])) {
            foreach (['attributeName', 'name', 'displayName'] as $key) {
                if (isset($row['attribute'][$key]) && is_scalar($row['attribute'][$key]) && trim((string) $row['attribute'][$key]) !== '') {
                    return (string) $row['attribute'][$key];
                }
            }
        }

        $id = $this->resolveA1AttributeId($row);

        if ($id !== '') {
            $definition = AttributeDefinition::query()->where('external_attribute_id', $id)->first();

            if ($definition) {
                return (string) ($definition->display_name ?: $definition->name ?: $id);
            }

            return $id;
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveA1AttributeId(array $row): string
    {
        foreach (['attributeId', 'AttributeId', 'productAttributeDefinitionId'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
        }

        if (isset($row['attribute']) && is_scalar($row['attribute']) && trim((string) $row['attribute']) !== '') {
            return (string) $row['attribute'];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveA1AttributeValue(array $row): string
    {
        $value = $row['value']
            ?? $row['Value']
            ?? $row['numericValue']
            ?? $row['numberValue']
            ?? $row['textValue']
            ?? null;

        if (is_array($value)) {
            return json_encode($value) ?: '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '—';
    }
}
