<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\CategoryAdminSearch;
use Illuminate\Console\Command;

class AnanasListBncCategoriesCommand extends Command
{
    protected $signature = 'bnc:ananas-list-bnc-categories
                            {--search= : Filter by category name}
                            {--limit=30 : Max rows}';

    protected $description = 'List BNC categories with IDs for Ananas category mapping setup';

    public function handle(): int
    {
        $search = trim((string) $this->option('search'));
        $limit = max(1, min(200, (int) $this->option('limit')));

        $query = Category::query()->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('display_name', 'like', '%'.$search.'%')
                    ->orWhere('full_slug', 'like', '%'.$search.'%');
            });
        }

        $categories = $query->limit($limit)->get();

        if ($categories->isEmpty()) {
            $this->warn('No BNC categories matched.');

            return self::FAILURE;
        }

        $this->info('BNC categories ('.$categories->count().' shown)');
        $this->newLine();

        $this->table(
            ['ID', 'Name', 'Path / slug', 'Products'],
            $categories->map(function (Category $category): array {
                $productCount = $category->products()->count();

                return [
                    (string) $category->id,
                    CategoryAdminSearch::formatOptionLabel($category),
                    (string) ($category->full_slug ?: $category->path ?: '—'),
                    (string) $productCount,
                ];
            })->all(),
        );

        $example = $categories->first();
        $this->newLine();
        $this->line('Example create mapping (Laptopi → ITShop):');
        $this->line(sprintf(
            '  php artisan bnc:ananas-create-category-mapping --category-id=%d --product-type=ITShop --ananas-category=Laptopi',
            $example->id,
        ));

        return self::SUCCESS;
    }
}
