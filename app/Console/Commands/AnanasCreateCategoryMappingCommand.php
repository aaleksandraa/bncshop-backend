<?php

namespace App\Console\Commands;

use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Models\Category;
use App\Services\Ananas\AnanasValidatedMappingService;
use App\Support\CategoryAdminSearch;
use Illuminate\Console\Command;

class AnanasCreateCategoryMappingCommand extends Command
{
    protected $signature = 'bnc:ananas-create-category-mapping
                            {--category-id= : BNC categories.id}
                            {--product-type= : Ananas product type, e.g. ITShop}
                            {--ananas-category= : Ananas subcategory string, e.g. Gaming laptopi}
                            {--enable : Enable mapping for export immediately}
                            {--no-descendants : Do not include descendant BNC categories}
                            {--update : Upsert if mapping for this BNC category already exists}';

    protected $description = 'Create or upsert Ananas BNC→Ananas category mapping (alternative to Filament admin)';

    public function handle(AnanasValidatedMappingService $mappingService): int
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
            $this->line('  php artisan bnc:ananas-create-category-mapping --category-id=199 --product-type=ITShop --ananas-category="Gaming laptopi" --enable');
            $this->line('Stage-validated set (199 + 231):');
            $this->line('  php artisan bnc:ananas-apply-validated-mappings');

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

        $existing = AnanasCategoryMapping::query()->where('category_id', $categoryId)->first();

        if ($existing instanceof AnanasCategoryMapping && ! $this->option('update')) {
            $this->error('Mapping for this BNC category already exists. Re-run with --update or use Filament admin.');
            $this->line("Existing mapping ID: {$existing->id} (Ananas category: ".($existing->ananas_category ?: '—').')');

            return self::FAILURE;
        }

        $mapping = $mappingService->upsert(
            categoryId: $categoryId,
            productType: $productType,
            ananasCategory: $ananasCategory !== '' ? $ananasCategory : null,
            enabled: (bool) $this->option('enable'),
            includeDescendants: ! $this->option('no-descendants'),
            validationStatus: $existing instanceof AnanasCategoryMapping
                ? (string) ($existing->category_validation_status ?: AnanasCategoryMapping::VALIDATION_UNKNOWN)
                : AnanasCategoryMapping::VALIDATION_UNKNOWN,
            preserveEnabledWhenUpdating: $existing instanceof AnanasCategoryMapping && ! $this->option('enable'),
        );

        $this->info($existing instanceof AnanasCategoryMapping
            ? 'Ananas category mapping updated.'
            : 'Ananas category mapping created.');
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
            $this->warn('Set --ananas-category before probe (e.g. Gaming laptopi, Nosači za televizor).');
        } else {
            $this->line('Next: dry-run import from admin (Ananas → Postavke) or:');
            $this->line('  php artisan bnc:ananas-import-products --limit=50 --dry-run');
        }

        return self::SUCCESS;
    }
}
