<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Support\CategoryAdminSearch;
use Illuminate\Console\Command;

class AnanasCreateCategoryMappingCommand extends Command
{
    protected $signature = 'bnc:ananas-create-category-mapping
                            {--category-id= : BNC categories.id}
                            {--product-type= : Ananas product type, e.g. ITShop}
                            {--ananas-category= : Ananas subcategory string, e.g. Laptopi}
                            {--enable : Enable mapping for export immediately}
                            {--no-descendants : Do not include descendant BNC categories}';

    protected $description = 'Create Ananas BNC→Ananas category mapping (alternative to Filament admin)';

    public function handle(): int
    {
        $categoryId = (int) $this->option('category-id');
        $productType = trim((string) $this->option('product-type'));
        $ananasCategory = trim((string) $this->option('ananas-category'));

        if ($categoryId <= 0 || $productType === '') {
            $this->error('Required: --category-id and --product-type');
            $this->newLine();
            $this->line('Step 1 — find BNC category ID:');
            $this->line('  php artisan bnc:ananas-list-bnc-categories --search=laptop');
            $this->newLine();
            $this->line('Step 2 — create mapping:');
            $this->line('  php artisan bnc:ananas-create-category-mapping --category-id=123 --product-type=ITShop --ananas-category=Laptopi');

            return self::FAILURE;
        }

        $category = Category::query()->find($categoryId);

        if ($category === null) {
            $this->error("BNC category #{$categoryId} not found.");

            return self::FAILURE;
        }

        if (! AnanasProductType::query()->where('name', $productType)->exists()) {
            $known = AnanasProductType::query()->orderBy('name')->pluck('name')->all();
            $this->warn("Product type \"{$productType}\" is not in cached Ananas list.");
            $this->line('Run: php artisan bnc:ananas-refresh-product-types');
            $this->line('Known types: '.implode(', ', $known));
        }

        if (AnanasCategoryMapping::query()->where('category_id', $categoryId)->exists()) {
            $this->error('Mapping for this BNC category already exists. Use Filament admin or delete the existing row first.');

            return self::FAILURE;
        }

        $mapping = AnanasCategoryMapping::query()->create([
            'category_id' => $categoryId,
            'ananas_product_type' => $productType,
            'ananas_category' => $ananasCategory !== '' ? $ananasCategory : null,
            'category_validation_status' => AnanasCategoryMapping::VALIDATION_UNKNOWN,
            'is_enabled' => (bool) $this->option('enable'),
            'include_descendants' => ! $this->option('no-descendants'),
        ]);

        $this->info('Ananas category mapping created.');
        $this->table(['Field', 'Value'], [
            ['Mapping ID', (string) $mapping->id],
            ['BNC category', CategoryAdminSearch::formatOptionLabel($category)],
            ['Product type', $productType],
            ['Ananas category', $ananasCategory !== '' ? $ananasCategory : '(empty — set before probe)'],
            ['Enabled', $mapping->is_enabled ? 'yes' : 'no'],
            ['Include descendants', $mapping->include_descendants ? 'yes' : 'no'],
        ]);

        $this->newLine();

        if ($ananasCategory === '') {
            $this->warn('Set --ananas-category before probe (e.g. Laptopi, Notebooki, …).');
        } else {
            $this->line('Next: dry-run probe');
            $this->line("  php artisan bnc:ananas-probe-category {$mapping->id} --dry-run");
        }

        return self::SUCCESS;
    }
}
