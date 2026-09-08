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
            ->with('category')
            ->orderBy('id')
            ->get();

        if ($mappings->isEmpty()) {
            $this->warn('No category mappings found.');
            $this->line('Create one via admin (Ananas → Mapiranje kategorija) or CLI:');
            $this->line('  php artisan bnc:ananas-list-bnc-categories --search=laptop');
            $this->line('  php artisan bnc:ananas-create-category-mapping --category-id=ID --product-type=ITShop --ananas-category=Laptopi');

            return self::FAILURE;
        }

        $this->info('Ananas category mappings ('.$mappings->count().')');
        $this->newLine();

        $this->table(
            ['ID', 'BNC category', 'Product type', 'Ananas category', 'Enabled', 'Validation'],
            $mappings->map(fn (AnanasCategoryMapping $mapping): array => [
                (string) $mapping->id,
                $mapping->category !== null
                    ? CategoryAdminSearch::formatOptionLabel($mapping->category)
                    : (string) $mapping->category_id,
                (string) $mapping->ananas_product_type,
                (string) ($mapping->ananas_category ?: '—'),
                $mapping->is_enabled ? 'yes' : 'no',
                (string) ($mapping->category_validation_status ?? 'unknown'),
            ])->all(),
        );

        $firstId = (int) $mappings->first()->id;
        $this->newLine();
        $this->line('Example dry-run probe:');
        $this->line("  php artisan bnc:ananas-probe-category {$firstId} --dry-run");

        return self::SUCCESS;
    }
}
