<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
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

        $remoteAttrs = is_array($remote['attributes'] ?? null) ? $remote['attributes'] : [];
        $this->newLine();
        $this->info('Live A1 attributes ('.count($remoteAttrs).'):');
        $this->table(
            ['A1 attributeName', 'value', 'Weight-like'],
            array_map(static function (array $row): array {
                $name = (string) ($row['attributeName'] ?? $row['attribute'] ?? '—');
                $value = is_array($row['value'] ?? null)
                    ? json_encode($row['value'])
                    : (string) ($row['value'] ?? '—');
                $weight = preg_match('/težin|tezin|weight|masa|bruto|neto/i', $name) === 1 ? 'yes' : 'no';

                return [$name, $value, $weight];
            }, $remoteAttrs) ?: [['—', 'A1 sent no attributes[]', '—']],
        );

        $a1Weight = collect($remoteAttrs)->first(fn (array $row): bool => preg_match(
            '/težin|tezin|weight|masa|bruto|neto/i',
            (string) ($row['attributeName'] ?? ''),
        ) === 1);

        if ($a1Weight === null) {
            $this->warn('A1 live payload has no weight attribute for this SKU. BNC cannot invent it.');
        } else {
            $this->info('A1 HAS weight: '.($a1Weight['attributeName'] ?? '').' = '.(is_scalar($a1Weight['value'] ?? null) ? $a1Weight['value'] : json_encode($a1Weight['value'] ?? null)));
        }

        return self::SUCCESS;
    }
}
