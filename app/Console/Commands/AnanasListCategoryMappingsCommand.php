<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Support\CategoryAdminSearch;
use Illuminate\Console\Command;

class AnanasListCategoryMappingsCommand extends Command
{
    protected $signature = 'bnc:ananas-list-category-mappings';

    protected $description = 'List Ananas BNC→Ananas category mappings with IDs for probe/import commands';

    public function handle(): int
    {
        $mappings = AnanasCategoryMapping::query()
            ->with(['category' => fn ($query) => $query->withCount('products')])
            ->orderBy('id')
            ->get();

        if ($mappings->isEmpty()) {
            $this->warn('No category mappings found.');
            $this->line('Create Stage-validated mappings (Gaming laptopi + Nosači za televizor):');
            $this->line('  php artisan bnc:ananas-apply-validated-mappings');
            $this->line('Or manually:');
            $this->line('  php artisan bnc:ananas-list-bnc-categories --search=laptop');
            $this->line('  php artisan bnc:ananas-create-category-mapping --category-id=199 --product-type=ITShop --ananas-category="Gaming laptopi" --enable');

            return self::FAILURE;
        }

        $enabled = $mappings->where('is_enabled', true)->count();
        $this->info('Ananas category mappings ('.$mappings->count().', enabled '.$enabled.')');
        $this->newLine();

        $this->table(
            ['ID', 'BNC ID', 'BNC category', 'SKU', 'Product type', 'Ananas category', 'Enabled', 'Validation'],
            $mappings->map(fn (AnanasCategoryMapping $mapping): array => [
                (string) $mapping->id,
                (string) $mapping->category_id,
                $mapping->category !== null
                    ? CategoryAdminSearch::formatOptionLabel($mapping->category)
                    : (string) $mapping->category_id,
                (string) ($mapping->category?->products_count ?? 0),
                (string) $mapping->ananas_product_type,
                (string) ($mapping->ananas_category ?: '—'),
                $mapping->is_enabled ? 'yes' : 'no',
                (string) ($mapping->category_validation_status ?? 'unknown'),
            ])->all(),
        );

        $firstEnabled = $mappings->first(fn (AnanasCategoryMapping $mapping): bool => $mapping->is_enabled);
        $exampleId = (int) ($firstEnabled?->id ?? $mappings->first()->id);
        $this->newLine();
        $this->line('Example dry-run probe:');
        $this->line("  php artisan bnc:ananas-probe-category {$exampleId} --dry-run");
        $this->line('Batch import (enabled mappings only):');
        $this->line('  php artisan bnc:ananas-import-products --limit=50 --dry-run');

        return self::SUCCESS;
    }
}
