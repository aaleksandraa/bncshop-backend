<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Ananas\AnanasPackageWeightResolver;
use Illuminate\Console\Command;

class AnanasWeightAuditCommand extends Command
{
    protected $signature = 'bnc:ananas-weight-audit
                            {--product= : Inspect one product ID (e.g. 1)}
                            {--scan=0 : Limit products for parse-status summary (0 = entire active catalog)}';

    protected $description = 'Audit BNC weight attributes vs AnanasPackageWeightResolver (names, units, parse results)';

    public function handle(AnanasPackageWeightResolver $resolver): int
    {
        $this->info('Ananas weight audit (local DB)');

        $definitions = $resolver->discoverWeightAttributeDefinitions();

        if ($definitions === []) {
            $this->warn('No attribute definitions matched težina/weight/masa patterns.');
        } else {
            $this->newLine();
            $this->info('Weight-like attribute definitions in DB:');
            $this->table(
                ['Def ID', 'Name', 'Display unit', 'In chain', 'Products', 'Sample raw values'],
                collect($definitions)->map(fn (array $row): array => [
                    (string) $row['definition_id'],
                    $row['display_name'] ?: $row['name'],
                    (string) ($row['display_unit'] ?: '—'),
                    $row['in_config_chain'] ? 'yes' : 'no',
                    (string) $row['product_value_count'],
                    $row['sample_raw_values'] !== [] ? implode(' | ', array_slice($row['sample_raw_values'], 0, 3)) : '—',
                ])->take(30)->all(),
            );
        }

        $this->newLine();
        $this->info('Configured chain (bnc.ananas_weight_attribute_names):');
        foreach ($resolver->approvedAttributeNames() as $name) {
            $this->line('  - '.$name);
        }

        $scan = (int) $this->option('scan');
        $this->newLine();
        $this->info('Parse status summary (active + public products'.($scan > 0 ? ", scan={$scan}" : ', full catalog').'):');
        $statuses = $resolver->summarizeParseStatusesForActiveProducts($scan);
        $this->table(['Parse status', 'Count'], collect($statuses)->map(fn (int $count, string $status): array => [$status, $count])->values()->all());

        $productId = $this->option('product');

        if (is_string($productId) && $productId !== '') {
            $this->inspectProduct($resolver, (int) $productId);
        }

        $this->newLine();
        $this->line('Common pattern: raw_value is numeric (e.g. "184") + display_unit on definition ("g").');
        $this->line('Resolver now combines normalized/raw value with display_unit before parsing.');

        return self::SUCCESS;
    }

    private function inspectProduct(AnanasPackageWeightResolver $resolver, int $productId): void
    {
        $product = Product::query()
            ->with(['attributeValues.attributeDefinition'])
            ->find($productId);

        if ($product === null) {
            $this->error("Product #{$productId} not found.");

            return;
        }

        $this->newLine();
        $this->info("Product #{$productId} weight attributes:");
        $rows = [];

        foreach ($product->attributeValues as $value) {
            $definition = $value->attributeDefinition?->resolveCanonical();
            $label = $definition?->display_name ?: $definition?->name ?: $value->attribute_name_snapshot;

            if (! preg_match('/težin|tezin|weight|masa|paket/i', (string) $label)) {
                continue;
            }

            $rows[] = [
                (string) $label,
                (string) ($value->raw_value ?: '—'),
                (string) ($value->normalized_value ?: '—'),
                (string) ($definition?->display_unit ?: '—'),
            ];
        }

        if ($rows === []) {
            $this->warn('  No weight-like attributes on this product.');
        } else {
            $this->table(['Attribute', 'raw_value', 'normalized_value', 'display_unit'], $rows);
        }

        $result = $resolver->resolve($product);
        $this->line(sprintf(
            'Resolver result: %s%s',
            $result->parseStatus,
            $result->isOk() ? ' → '.$result->resolvedWeightKg.' kg from '.$result->sourceAttributeName : (' — '.($result->reason ?? '')),
        ));
    }
}
